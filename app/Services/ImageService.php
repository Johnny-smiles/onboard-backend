<?php
namespace App\Services;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;
use App\Models\Photo;
use Illuminate\Support\Str;
class ImageService
{
    public function optimizeAndStore($uploadedFile, $clientId): string
    {
        $image = Image::make($uploadedFile);
        $image->resize(2048, null, function ($c) { $c->aspectRatio(); $c->upsize(); });
        $filename = Str::uuid()->toString().'.jpg';
        $path = "photos/clients/{$clientId}/{$filename}";
        Storage::disk(config('filesystems.default'))->put($path, (string) $image->encode('jpg', 85));
        return $path;
    }
    public function scoreImage(Photo $photo): int
    {
        try {
            $raw = Storage::disk(config('filesystems.default'))->get($photo->file_path);
            $img = Image::make($raw)->resize(32, 32);
            $brightness = 0; $n = 0;
            for ($x=0; $x<32; $x++) for ($y=0; $y<32; $y++) {
                $color = $img->pickColor($x,$y,'array');
                $brightness += ($color[0]+$color[1]+$color[2])/3/255; $n++;
            }
            $score = (int) max(0,min(100, round(($brightness/$n)*100)));
        } catch (\Throwable $e) { $score = 0; }
        $photo->update(['quality_score' => $score]);
        return $score;
    }
}
