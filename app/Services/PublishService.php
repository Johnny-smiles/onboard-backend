<?php

namespace App\Services;

use App\Models\Photo;
use App\Models\PhotoPublication;
use App\Models\SocialIntegration;
use App\Models\User;
use App\Notifications\PublishingFailedNotification;
use Carbon\Carbon;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PublishService
{
    public function __construct(
        private readonly SocialIntegrationService $integrationService
    ) {
    }

    /**
     * Queue publications for later processing
     */
    public function queue(array $photoIds, string $service, ?string $when = null, ?int $clientId = null): array
    {
        $scheduled = $when ? Carbon::parse($when) : now();
        $ids = [];

        foreach ($photoIds as $pid) {
            $photo = Photo::find($pid);

            if (!$photo) {
                continue;
            }

            $pub = PhotoPublication::create([
                'photo_id' => $pid,
                'service' => $service,
                'status' => 'queued',
                'scheduled_at' => $scheduled,
                'payload' => [
                    'client_id' => $clientId ?? $photo->client_id,
                    'caption' => $photo->caption ?? '',
                ],
            ]);

            $ids[] = $pub->id;
        }

        return $ids;
    }

    /**
     * Process all due publications
     */
    public function dispatchDue(): int
    {
        $due = PhotoPublication::where('status', 'queued')
            ->where('scheduled_at', '<=', now())
            ->get();

        $count = 0;

        foreach ($due as $pub) {
            try {
                $this->publish($pub);
                $count++;
            } catch (\Exception $e) {
                Log::error("Failed to publish {$pub->id}", [
                    'error' => $e->getMessage(),
                ]);

                // Increment retry count
                $retryCount = ($pub->payload['retry_count'] ?? 0) + 1;
                $maxRetries = 5;

                if ($retryCount >= $maxRetries) {
                    $pub->update([
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                        'payload' => array_merge($pub->payload ?? [], [
                            'retry_count' => $retryCount,
                            'error' => $e->getMessage(),
                        ]),
                    ]);

                    // Notify client users about the failure
                    $clientId = $pub->payload['client_id'] ?? $pub->photo->client_id;
                    if ($clientId) {
                        $users = User::where('client_id', $clientId)->get();
                        foreach ($users as $user) {
                            $user->notify(new PublishingFailedNotification($pub));
                        }
                    }
                } else {
                    // Exponential backoff: 1m, 5m, 15m, 1h, 4h
                    $delays = [60, 300, 900, 3600, 14400];
                    $delay = $delays[$retryCount - 1] ?? 3600;

                    $pub->update([
                        'scheduled_at' => now()->addSeconds($delay),
                        'payload' => array_merge($pub->payload ?? [], [
                            'retry_count' => $retryCount,
                            'last_error' => $e->getMessage(),
                        ]),
                    ]);
                }
            }
        }

        return $count;
    }

    /**
     * Publish a single photo publication
     */
    private function publish(PhotoPublication $publication): void
    {
        $photo = $publication->photo;

        if (!$photo) {
            throw new \Exception('Photo not found');
        }

        $clientId = $publication->payload['client_id'] ?? $photo->client_id;

        if (!$clientId) {
            throw new \Exception('Client ID not found');
        }

        $integration = SocialIntegration::where('client_id', $clientId)
            ->where('provider', $publication->service)
            ->where('status', 'active')
            ->first();

        if (!$integration) {
            throw new \Exception("No active {$publication->service} integration found for client {$clientId}");
        }

        // Refresh token if needed
        $this->integrationService->refreshIfNeeded($integration);

        $accessToken = $this->integrationService->getAccessToken($integration);

        if (!$accessToken) {
            throw new \Exception('No access token available');
        }

        // Publish based on service
        match ($publication->service) {
            'meta' => $this->publishToMeta($photo, $integration, $accessToken, $publication),
            'google', 'gbp' => $this->publishToGoogle($photo, $integration, $accessToken, $publication),
            'wordpress' => $this->publishToWordPress($photo, $integration, $accessToken, $publication),
            default => throw new \Exception("Unsupported service: {$publication->service}")
        };
    }

    /**
     * Publish to Meta (Facebook Page or Instagram)
     */
    private function publishToMeta(Photo $photo, SocialIntegration $integration, string $accessToken, PhotoPublication $publication): void
    {
        $externalIds = $integration->external_ids ?? [];
        $pageId = $externalIds['page_id'] ?? null;
        $igBusinessId = $externalIds['instagram_business_id'] ?? null;

        if (!$pageId) {
            throw new \Exception('No Facebook page ID configured');
        }

        $httpClient = new HttpClient();
        $caption = $publication->payload['caption'] ?? $photo->caption ?? '';

        // Get photo URL
        $photoUrl = Storage::url($photo->file_path);
        if (!str_starts_with($photoUrl, 'http')) {
            $photoUrl = config('app.url').$photoUrl;
        }

        // Publish to Facebook Page
        $response = $httpClient->post("https://graph.facebook.com/v18.0/{$pageId}/photos", [
            'form_params' => [
                'url' => $photoUrl,
                'caption' => $caption,
                'access_token' => $accessToken,
            ],
        ]);

        $result = json_decode($response->getBody(), true);
        $postId = $result['id'] ?? null;

        $publication->update([
            'status' => 'published',
            'published_at' => now(),
            'payload' => array_merge($publication->payload ?? [], [
                'external_id' => $postId,
                'platform' => 'facebook',
                'post_url' => "https://facebook.com/{$postId}",
            ]),
        ]);

        Log::info("Published photo {$photo->id} to Facebook page {$pageId}", [
            'post_id' => $postId,
        ]);

        // TODO: Also publish to Instagram if igBusinessId is available
        // Instagram requires a 2-step process: create container, then publish
    }

    /**
     * Publish to Google Business Profile
     */
    private function publishToGoogle(Photo $photo, SocialIntegration $integration, string $accessToken, PhotoPublication $publication): void
    {
        $externalIds = $integration->external_ids ?? [];
        $location = $externalIds['primary_location'] ?? $externalIds['locations'][0] ?? null;

        if (!$location) {
            throw new \Exception('No Google Business location configured');
        }

        $caption = $publication->payload['caption'] ?? $photo->caption ?? '';
        $photoUrl = Storage::url($photo->file_path);

        if (!str_starts_with($photoUrl, 'http')) {
            $photoUrl = config('app.url').$photoUrl;
        }

        // Initialize Google Client
        $googleClient = new \Google_Client();
        $googleClient->setAccessToken(['access_token' => $accessToken]);

        $service = new \Google_Service_MyBusiness($googleClient);

        // Create local post with media
        $localPost = new \Google_Service_MyBusiness_LocalPost();
        $localPost->setSummary($caption);

        $mediaItem = new \Google_Service_MyBusiness_MediaItem();
        $mediaItem->setSourceUrl($photoUrl);
        $mediaItem->setMediaFormat('PHOTO');

        $localPost->setMedia([$mediaItem]);

        $result = $service->accounts_locations_localPosts->create($location, $localPost);

        $publication->update([
            'status' => 'published',
            'published_at' => now(),
            'payload' => array_merge($publication->payload ?? [], [
                'external_id' => $result->getName(),
                'platform' => 'google_business_profile',
            ]),
        ]);

        Log::info("Published photo {$photo->id} to Google Business Profile", [
            'location' => $location,
        ]);
    }

    /**
     * Publish to WordPress
     */
    private function publishToWordPress(Photo $photo, SocialIntegration $integration, string $accessToken, PhotoPublication $publication): void
    {
        $externalIds = $integration->external_ids ?? [];
        $siteUrl = $externalIds['site_url'] ?? null;

        if (!$siteUrl) {
            throw new \Exception('No WordPress site URL configured');
        }

        $httpClient = new HttpClient();
        $caption = $publication->payload['caption'] ?? $photo->caption ?? '';

        // Step 1: Upload media
        $photoPath = Storage::path($photo->file_path);

        if (!file_exists($photoPath)) {
            throw new \Exception('Photo file not found');
        }

        $mediaResponse = $httpClient->post("{$siteUrl}/wp-json/wp/v2/media", [
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
            ],
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => fopen($photoPath, 'r'),
                    'filename' => basename($photoPath),
                ],
            ],
        ]);

        $mediaData = json_decode($mediaResponse->getBody(), true);
        $mediaId = $mediaData['id'] ?? null;

        if (!$mediaId) {
            throw new \Exception('Failed to upload media to WordPress');
        }

        // Step 2: Create post with media
        $postResponse = $httpClient->post("{$siteUrl}/wp-json/wp/v2/posts", [
            'headers' => [
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'title' => $caption ?: 'Photo Post',
                'content' => $caption,
                'status' => 'publish',
                'featured_media' => $mediaId,
            ],
        ]);

        $postData = json_decode($postResponse->getBody(), true);
        $postId = $postData['id'] ?? null;

        $publication->update([
            'status' => 'published',
            'published_at' => now(),
            'payload' => array_merge($publication->payload ?? [], [
                'external_id' => $postId,
                'media_id' => $mediaId,
                'platform' => 'wordpress',
                'post_url' => $postData['link'] ?? null,
            ]),
        ]);

        Log::info("Published photo {$photo->id} to WordPress", [
            'post_id' => $postId,
            'site_url' => $siteUrl,
        ]);
    }
}
