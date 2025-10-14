<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{
    AuthController, ClientController, ProjectController, PhotoController,
    PhotoGuideController, ReminderController, NotificationController,
    PhotoBulkController, PhotoTagController, PhotoCommentController,
    PublishController, CaptionController
};
Route::prefix('v1')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::apiResource('clients', ClientController::class);
        Route::apiResource('projects', ProjectController::class);
        Route::apiResource('photos', PhotoController::class)->only(['index','store','destroy']);
        Route::post('photos/{photo}/approve', [PhotoController::class, 'approve']);
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
        // Publishing stubs
        Route::post('publish/wordpress', [PublishController::class, 'queueWordpress']);
        Route::post('publish/meta', [PublishController::class, 'queueMeta']);
        Route::post('publish/gbp', [PublishController::class, 'queueGBP']);
        Route::post('publish/process-due', [PublishController::class, 'processDue']);
        // Captions
        Route::post('photos/{photo}/suggest-caption', [CaptionController::class, 'suggest']);
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/mark-read', [NotificationController::class, 'markRead']);
    });
});
