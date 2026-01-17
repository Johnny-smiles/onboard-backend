<?php

namespace App\Jobs;

use App\Models\Photo;
use App\Services\ImageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScoreImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $photoId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ImageService $imageService): void
    {
        $photo = Photo::find($this->photoId);

        if (!$photo) {
            Log::warning("ScoreImageJob: Photo {$this->photoId} not found");
            return;
        }

        $score = $imageService->scoreImage($photo);

        Log::info("ScoreImageJob: Scored photo {$this->photoId} with score {$score}");
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ScoreImageJob failed for photo {$this->photoId}", [
            'error' => $exception->getMessage(),
        ]);
    }
}
