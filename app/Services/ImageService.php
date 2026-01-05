<?php

namespace App\Services;

use App\Models\Photo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ImageService
{
    protected ImageManager $manager;

    public function __construct()
    {
        // Initialize ImageManager with GD driver
        // Use Imagick driver if available: new Intervention\Image\Drivers\Imagick\Driver()
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Optimize, strip EXIF data, and store uploaded image
     *
     * Security: Strips all EXIF data including GPS coordinates, camera info, etc.
     */
    public function optimizeAndStore($uploadedFile, $clientId): string
    {
        // Read the uploaded image
        $image = $this->manager->read($uploadedFile);

        // Resize to max 2048px width while maintaining aspect ratio
        // This also reduces file size significantly
        $image->scale(width: 2048);

        $filename = Str::uuid()->toString().'.jpg';
        $path = "photos/clients/{$clientId}/{$filename}";

        // Encode to JPEG with 85% quality
        // IMPORTANT: Re-encoding strips all EXIF metadata (GPS, camera info, etc.)
        $encoded = $image->toJpeg(quality: 85);

        Storage::disk(config('filesystems.default'))->put($path, (string) $encoded);

        // Log EXIF stripping for security audit
        activity()
            ->withProperties([
                'original_filename' => $uploadedFile->getClientOriginalName(),
                'client_id' => $clientId,
                'exif_stripped' => true,
            ])
            ->log('Photo uploaded with EXIF data stripped');

        return $path;
    }

    /**
     * Calculate basic quality score based on brightness
     */
    public function scoreImage(Photo $photo): int
    {
        try {
            $raw = Storage::disk(config('filesystems.default'))->get($photo->file_path);
            $img = $this->manager->read($raw)->scale(width: 32, height: 32);

            $brightness = 0;
            $n = 0;

            // Sample pixel brightness across the image
            for ($x = 0; $x < 32; $x++) {
                for ($y = 0; $y < 32; $y++) {
                    $color = $img->pickColor($x, $y)->toArray();
                    $brightness += ($color[0] + $color[1] + $color[2]) / 3 / 255;
                    $n++;
                }
            }

            $score = (int) max(0, min(100, round(($brightness / $n) * 100)));
        } catch (\Throwable $e) {
            $score = 0;
        }

        $photo->update(['quality_score' => $score]);

        return $score;
    }
}
