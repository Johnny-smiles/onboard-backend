<?php

namespace App\Services;

use App\Models\Client;
use App\Models\SocialIntegration;
use Facebook\Facebook;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class SocialIntegrationService
{
    public function upsertForClient(Client $client, array $data): SocialIntegration
    {
        if (empty($data['provider'])) {
            throw new InvalidArgumentException('Provider is required.');
        }

        $attributes = [
            'client_id' => $client->id,
            'connected_at' => now(),
        ];

        foreach (['account_name', 'external_ids', 'scopes', 'expires_at', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $data[$field];
            }
        }

        if (array_key_exists('access_token', $data)) {
            $attributes['access_token_encrypted'] = $data['access_token'] !== null
                ? Crypt::encryptString($data['access_token'])
                : null;
        }

        if (array_key_exists('refresh_token', $data)) {
            $attributes['refresh_token_encrypted'] = $data['refresh_token'] !== null
                ? Crypt::encryptString($data['refresh_token'])
                : null;
        }

        return SocialIntegration::updateOrCreate(
            ['client_id' => $client->id, 'provider' => $data['provider']],
            $attributes
        );
    }

    /**
     * Handle Meta (Facebook) OAuth callback
     */
    public function handleMetaCallback(Client $client, SocialiteUser $user): array
    {
        $shortToken = $user->token;
        $appId = config('services.facebook.client_id');
        $appSecret = config('services.facebook.client_secret');

        // Exchange short-lived token for long-lived user token
        $longLivedTokenUrl = sprintf(
            'https://graph.facebook.com/oauth/access_token?grant_type=fb_exchange_token&client_id=%s&client_secret=%s&fb_exchange_token=%s',
            $appId,
            $appSecret,
            $shortToken
        );

        $httpClient = new HttpClient();
        $response = $httpClient->get($longLivedTokenUrl);
        $tokenData = json_decode($response->getBody(), true);

        $longLivedToken = $tokenData['access_token'] ?? $shortToken;
        $expiresIn = $tokenData['expires_in'] ?? 5184000; // 60 days default

        // Get user's Facebook Pages
        $pagesUrl = "https://graph.facebook.com/v18.0/me/accounts?access_token={$longLivedToken}";
        $pagesResponse = $httpClient->get($pagesUrl);
        $pagesData = json_decode($pagesResponse->getBody(), true);

        $pages = $pagesData['data'] ?? [];

        // Get Instagram Business accounts for each page
        $externalIds = [];
        foreach ($pages as $page) {
            $pageId = $page['id'];
            $igUrl = "https://graph.facebook.com/v18.0/{$pageId}?fields=instagram_business_account&access_token={$longLivedToken}";

            try {
                $igResponse = $httpClient->get($igUrl);
                $igData = json_decode($igResponse->getBody(), true);

                if (isset($igData['instagram_business_account'])) {
                    $externalIds['instagram_business_id'] = $igData['instagram_business_account']['id'];
                }
            } catch (\Exception $e) {
                Log::warning("No Instagram account for page {$pageId}");
            }

            $externalIds['page_id'] = $pageId;
            $externalIds['page_name'] = $page['name'];
            break; // Use first page for now
        }

        // Store integration
        $integration = $this->upsertForClient($client, [
            'provider' => 'meta',
            'account_name' => $user->getName() ?? $user->getEmail(),
            'external_ids' => $externalIds,
            'scopes' => [
                'pages_show_list',
                'pages_manage_posts',
                'instagram_basic',
                'instagram_content_publish',
            ],
            'access_token' => $longLivedToken,
            'refresh_token' => null, // Meta doesn't use refresh tokens
            'expires_at' => now()->addSeconds($expiresIn),
            'status' => 'active',
        ]);

        return [
            'client_id' => $client->id,
            'provider' => 'meta',
            'integration_id' => $integration->id,
            'pages' => $pages,
            'selected_page' => $externalIds['page_name'] ?? null,
        ];
    }

    /**
     * Handle Google OAuth callback
     */
    public function handleGoogleCallback(Client $client, SocialiteUser $user): array
    {
        $accessToken = $user->token;
        $refreshToken = $user->refreshToken;
        $expiresIn = $user->expiresIn ?? 3600;

        // Initialize Google Client
        $googleClient = new \Google_Client();
        $googleClient->setClientId(config('services.google.client_id'));
        $googleClient->setClientSecret(config('services.google.client_secret'));
        $googleClient->setAccessToken([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => $expiresIn,
        ]);

        // Get Google Business Profile locations
        $locations = [];
        try {
            $service = new \Google_Service_MyBusiness($googleClient);
            $accounts = $service->accounts->listAccounts();

            foreach ($accounts->getAccounts() as $account) {
                $accountName = $account->getName();
                $locationsList = $service->accounts_locations->listAccountsLocations($accountName);

                foreach ($locationsList->getLocations() as $location) {
                    $locations[] = [
                        'name' => $location->getName(),
                        'title' => $location->getLocationName(),
                        'address' => $location->getAddress(),
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning('Could not fetch GBP locations', [
                'error' => $e->getMessage(),
            ]);
        }

        $externalIds = [];
        if (!empty($locations)) {
            $externalIds['locations'] = array_column($locations, 'name');
            $externalIds['primary_location'] = $locations[0]['name'] ?? null;
        }

        // Store integration
        $integration = $this->upsertForClient($client, [
            'provider' => 'google',
            'account_name' => $user->getName() ?? $user->getEmail(),
            'external_ids' => $externalIds,
            'scopes' => ['https://www.googleapis.com/auth/business.manage'],
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => now()->addSeconds($expiresIn),
            'status' => 'active',
        ]);

        return [
            'client_id' => $client->id,
            'provider' => 'google',
            'integration_id' => $integration->id,
            'locations' => $locations,
        ];
    }

    /**
     * Refresh access tokens if needed
     */
    public function refreshIfNeeded(SocialIntegration $integration): void
    {
        // Skip if token doesn't expire soon
        if ($integration->expires_at && $integration->expires_at->isFuture()) {
            return;
        }

        try {
            if ($integration->provider === 'google') {
                $this->refreshGoogleToken($integration);
            } elseif ($integration->provider === 'meta') {
                $this->refreshMetaToken($integration);
            }
        } catch (\Exception $e) {
            Log::error("Token refresh failed for integration {$integration->id}", [
                'provider' => $integration->provider,
                'error' => $e->getMessage(),
            ]);

            $integration->update(['status' => 'error']);
        }
    }

    /**
     * Refresh Google access token using refresh token
     */
    private function refreshGoogleToken(SocialIntegration $integration): void
    {
        $refreshToken = Crypt::decryptString($integration->refresh_token_encrypted);

        $googleClient = new \Google_Client();
        $googleClient->setClientId(config('services.google.client_id'));
        $googleClient->setClientSecret(config('services.google.client_secret'));
        $googleClient->refreshToken($refreshToken);

        $newToken = $googleClient->getAccessToken();

        $integration->update([
            'access_token_encrypted' => Crypt::encryptString($newToken['access_token']),
            'expires_at' => now()->addSeconds($newToken['expires_in'] ?? 3600),
            'status' => 'active',
        ]);

        Log::info("Refreshed Google token for integration {$integration->id}");
    }

    /**
     * Refresh Meta long-lived token (exchange old for new)
     */
    private function refreshMetaToken(SocialIntegration $integration): void
    {
        $oldToken = Crypt::decryptString($integration->access_token_encrypted);
        $appId = config('services.facebook.client_id');
        $appSecret = config('services.facebook.client_secret');

        $url = sprintf(
            'https://graph.facebook.com/oauth/access_token?grant_type=fb_exchange_token&client_id=%s&client_secret=%s&fb_exchange_token=%s',
            $appId,
            $appSecret,
            $oldToken
        );

        $httpClient = new HttpClient();
        $response = $httpClient->get($url);
        $data = json_decode($response->getBody(), true);

        $newToken = $data['access_token'] ?? null;
        $expiresIn = $data['expires_in'] ?? 5184000;

        if ($newToken) {
            $integration->update([
                'access_token_encrypted' => Crypt::encryptString($newToken),
                'expires_at' => now()->addSeconds($expiresIn),
                'status' => 'active',
            ]);

            Log::info("Refreshed Meta token for integration {$integration->id}");
        }
    }

    /**
     * Get decrypted access token for an integration
     */
    public function getAccessToken(SocialIntegration $integration): ?string
    {
        if (!$integration->access_token_encrypted) {
            return null;
        }

        return Crypt::decryptString($integration->access_token_encrypted);
    }
}
