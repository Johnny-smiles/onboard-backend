<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\SocialIntegration;
use App\Services\SocialIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialIntegrationController extends Controller
{
    public function __construct(private readonly SocialIntegrationService $service)
    {
    }

    public function index(Request $request, Client $client): JsonResponse
    {
        $this->authorizeClientAccess($request, $client);

        $integrations = SocialIntegration::where('client_id', $client->id)->get();

        return response()->json($integrations);
    }

    public function store(Request $request, Client $client): JsonResponse
    {
        $this->authorizeClientAccess($request, $client);

        $data = $request->validate([
            'provider' => ['required', 'string'],
            'account_name' => ['nullable', 'string'],
            'external_ids' => ['nullable', 'array'],
            'scopes' => ['nullable', 'array'],
            'access_token' => ['nullable', 'string'],
            'refresh_token' => ['nullable', 'string'],
            'expires_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string'],
        ]);

        $integration = $this->service->upsertForClient($client, $data);

        return response()->json($integration, 201);
    }

    public function destroy(Request $request, Client $client, SocialIntegration $integration): JsonResponse
    {
        $this->authorizeClientAccess($request, $client);

        if ($integration->client_id !== $client->id) {
            abort(404);
        }

        $integration->delete();

        return response()->json(['deleted' => true]);
    }

    public function redirect(Request $request, Client $client, string $provider): JsonResponse
    {
        $this->authorizeClientAccess($request, $client);

        $url = $this->service->getRedirectUrl($provider, $client);

        return response()->json(['url' => $url]);
    }

    protected function authorizeClientAccess(Request $request, Client $client): void
    {
        $user = $request->user();

        if (!$user) {
            abort(401);
        }

        if ($user->role === 'admin') {
            return;
        }

        if ((int) $user->client_id === $client->id) {
            return;
        }

        abort(403);
    }
}
