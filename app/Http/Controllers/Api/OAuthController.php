<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\SocialIntegrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class OAuthController extends Controller
{
    public function __construct(private readonly SocialIntegrationService $service)
    {
    }

    /**
     * Redirect to provider OAuth page
     */
    public function redirect(Request $request, string $provider)
    {
        $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
        ]);

        $clientId = $request->input('client_id');
        $user = $request->user();

        // Authorize access
        if ($user->role !== 'admin' && (int) $user->client_id !== (int) $clientId) {
            abort(403, 'Unauthorized to connect integrations for this client');
        }

        // Generate state token with client_id + random string for CSRF
        $state = base64_encode(json_encode([
            'client_id' => $clientId,
            'nonce' => bin2hex(random_bytes(16)),
        ]));

        // Store state in cache for 10 minutes
        Cache::put("oauth_state:{$state}", $clientId, 600);

        try {
            if ($provider === 'meta' || $provider === 'facebook') {
                return Socialite::driver('facebook')
                    ->scopes([
                        'public_profile',
                        'email',
                        'pages_show_list',
                        'pages_read_engagement',
                        'pages_manage_posts',
                        'instagram_basic',
                        'instagram_content_publish',
                    ])
                    ->with(['state' => $state])
                    ->redirect();
            }

            if ($provider === 'google') {
                return Socialite::driver('google')
                    ->scopes([
                        'https://www.googleapis.com/auth/business.manage',
                    ])
                    ->with([
                        'state' => $state,
                        'access_type' => 'offline',
                        'prompt' => 'consent',
                    ])
                    ->redirect();
            }

            abort(400, "Unsupported provider: {$provider}");
        } catch (\Exception $e) {
            Log::error("OAuth redirect error for {$provider}", [
                'error' => $e->getMessage(),
                'client_id' => $clientId,
            ]);

            return response()->json([
                'error' => 'Failed to initialize OAuth flow',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle OAuth callback from provider
     */
    public function callback(Request $request, string $provider)
    {
        try {
            $state = $request->input('state');

            if (!$state) {
                return $this->errorRedirect('Missing state parameter');
            }

            // Verify state
            $clientId = Cache::pull("oauth_state:{$state}");

            if (!$clientId) {
                return $this->errorRedirect('Invalid or expired state token');
            }

            $client = Client::findOrFail($clientId);

            // Get user from OAuth provider
            if ($provider === 'meta' || $provider === 'facebook') {
                $providerUser = Socialite::driver('facebook')->user();

                // Exchange for long-lived token and get pages/IG accounts
                $result = $this->service->handleMetaCallback($client, $providerUser);

                return $this->successRedirect($provider, $result);
            }

            if ($provider === 'google') {
                $providerUser = Socialite::driver('google')->stateless()->user();

                // Get GBP locations
                $result = $this->service->handleGoogleCallback($client, $providerUser);

                return $this->successRedirect($provider, $result);
            }

            return $this->errorRedirect("Unsupported provider: {$provider}");
        } catch (\Exception $e) {
            Log::error("OAuth callback error for {$provider}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorRedirect($e->getMessage());
        }
    }

    /**
     * Success redirect back to portal
     */
    private function successRedirect(string $provider, array $result): \Illuminate\Http\RedirectResponse
    {
        $message = urlencode("Successfully connected {$provider}!");
        $data = urlencode(json_encode($result));

        return redirect("/portal/admin/clients/{$result['client_id']}/social?success={$message}&data={$data}");
    }

    /**
     * Error redirect back to portal
     */
    private function errorRedirect(string $error): \Illuminate\Http\RedirectResponse
    {
        $message = urlencode($error);
        return redirect("/portal/admin/clients?error={$message}");
    }
}
