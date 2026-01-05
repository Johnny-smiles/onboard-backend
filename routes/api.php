<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{
    AuthController, ClientController, ProjectController, PhotoController,
    PhotoGuideController, ReminderController, NotificationController,
    PhotoBulkController, PhotoTagController, PhotoCommentController,
    PublishController, CaptionController, SocialIntegrationController,
    PhotoReviewController, ShotRecipeController, CaptureReminderController,
    ClientCaptureController, OAuthController, HealthController, AnalyticsController,
    TwoFactorController
};
// Health check (no auth required, for monitoring)
Route::get('health', [HealthController::class, 'check'])->name('health.check');

Route::prefix('v1')->group(function () {
    // More restrictive rate limiting for auth endpoints
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('2fa/verify', [TwoFactorController::class, 'verify']); // For login flow
    });

    Route::middleware('auth:sanctum')->group(function () {
        // Two-Factor Authentication
        Route::get('2fa/setup', [TwoFactorController::class, 'setup']);
        Route::post('2fa/enable', [TwoFactorController::class, 'enable']);
        Route::post('2fa/disable', [TwoFactorController::class, 'disable']);
        Route::post('2fa/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes']);
        // OAuth routes
        Route::get('integrations/{provider}/redirect', [OAuthController::class, 'redirect']);

        Route::post('logout', [AuthController::class, 'logout']);
        Route::apiResource('clients', ClientController::class);
        Route::apiResource('projects', ProjectController::class);
        Route::apiResource('photos', PhotoController::class)->only(['index','store','destroy']);
        Route::post('photos/{photo}/approve', [PhotoController::class, 'approve']);
        Route::get('review/photos', [PhotoReviewController::class, 'index']);
        Route::post('review/photos/{photo}/approve', [PhotoReviewController::class, 'approve']);
        Route::post('review/photos/{photo}/reject', [PhotoReviewController::class, 'reject']);
        Route::apiResource('shot-recipes', ShotRecipeController::class);
        Route::post('capture-reminders/run-due', [CaptureReminderController::class, 'runDue']);
        Route::apiResource('capture-reminders', CaptureReminderController::class);
        Route::get('capture/recipes', [ClientCaptureController::class, 'recipes']);
        Route::get('capture/needs', [ClientCaptureController::class, 'needs']);
        // Tags
        Route::post('photos/{photo}/tags', [PhotoTagController::class, 'add']);
        Route::delete('photos/{photo}/tags', [PhotoTagController::class, 'remove']);
        // Comments
        Route::get('photos/{photo}/comments', [PhotoCommentController::class, 'index']);
        Route::post('photos/{photo}/comments', [PhotoCommentController::class, 'store']);
        // Bulk ops
        Route::post('photos/bulk/approve', [PhotoBulkController::class, 'approve']);
        Route::post('photos/bulk/delete', [PhotoBulkController::class, 'delete']);
        Route::post('photos/bulk/export', [PhotoBulkController::class, 'export']);
        // Publishing and scheduling
        Route::post('publish/queue', [PublishController::class, 'queue']); // Generic queue endpoint
        Route::post('publish/wordpress', [PublishController::class, 'queueWordpress']);
        Route::post('publish/meta', [PublishController::class, 'queueMeta']);
        Route::post('publish/gbp', [PublishController::class, 'queueGBP']);
        Route::post('publish/process-due', [PublishController::class, 'processDue']);

        // Scheduled publications management
        Route::get('publish/scheduled', [PublishController::class, 'scheduled']); // Calendar view
        Route::patch('publications/{publication}/reschedule', [PublishController::class, 'reschedule']);
        Route::delete('publications/{publication}/cancel', [PublishController::class, 'cancel']);
        // Captions
        Route::post('photos/{photo}/suggest-caption', [CaptionController::class, 'suggest']);
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/mark-read', [NotificationController::class, 'markRead']);

        // Analytics
        Route::get('analytics/dashboard', [AnalyticsController::class, 'dashboard']);
        Route::get('analytics/publishing', [AnalyticsController::class, 'publishingPerformance']);
        Route::get('system/status', [HealthController::class, 'status']); // Admin only, detailed status

        // Social integrations
        Route::get('clients/{client}/integrations', [SocialIntegrationController::class, 'index']);
        Route::post('clients/{client}/integrations', [SocialIntegrationController::class, 'store']);
        Route::delete('clients/{client}/integrations/{integration}', [SocialIntegrationController::class, 'destroy']);
        Route::get('clients/{client}/integrations/{provider}/redirect', [SocialIntegrationController::class, 'redirect']);
    });
});

// OAuth callbacks (outside v1 group, no auth required)
Route::get('oauth/callback/{provider}', [OAuthController::class, 'callback'])->name('oauth.callback');
