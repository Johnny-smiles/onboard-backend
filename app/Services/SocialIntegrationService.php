<?php

namespace App\Services;

use App\Models\Client;
use App\Models\SocialIntegration;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;

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

    public function getRedirectUrl(string $provider, Client $client): string
    {
        // TODO: replace with Socialite redirect (see README_SOCIAL_INTEGRATIONS.md)
        return sprintf('/oauth/callback/%s?client_id=%d', $provider, $client->id);
    }

    public function refreshIfNeeded(SocialIntegration $integration): void
    {
        // TODO: Google: use refresh token; Meta: long-lived tokens; see README_SOCIAL_INTEGRATIONS.md
    }
}
