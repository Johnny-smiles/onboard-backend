<?php

namespace App\Jobs;

use App\Models\SocialIntegration;
use App\Models\User;
use App\Notifications\SocialTokenExpiringNotification;
use App\Services\SocialIntegrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshSocialTokens implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * Execute the job.
     */
    public function handle(SocialIntegrationService $service): void
    {
        Log::info('Refreshing social media tokens');

        $integrations = SocialIntegration::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDay())
            ->get();

        $refreshed = 0;

        foreach ($integrations as $integration) {
            try {
                $service->refreshIfNeeded($integration);
                $refreshed++;
            } catch (\Exception $e) {
                Log::error("Failed to refresh token for integration {$integration->id}", [
                    'provider' => $integration->provider,
                    'error' => $e->getMessage(),
                ]);

                // Notify admins if token refresh failed and it's expiring soon
                $hoursUntilExpiry = now()->diffInHours($integration->expires_at, false);
                if ($hoursUntilExpiry <= 48) {
                    // Notify all admins about the expiring token
                    $admins = User::admins()->get();
                    foreach ($admins as $admin) {
                        $admin->notify(new SocialTokenExpiringNotification($integration));
                    }
                }
            }
        }

        Log::info("Refreshed {$refreshed} social media tokens out of {$integrations->count()} checked");
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('RefreshSocialTokens job failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
