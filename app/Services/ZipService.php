<?php
namespace App\Services;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
class ZipService
{
    public function createFromPaths(array $paths, string $zipName): string
    {
        $dir = storage_path('app/public/exports');
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $zipPath = $dir . '/' . $zipName;
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            throw new \RuntimeException('Could not create ZIP');
        }
        foreach ($paths as $p) {
            $content = Storage::disk(config('filesystems.default'))->get($p);
            $zip->addFromString(basename($p), $content);
        }
        $zip->close();
        return 'exports/' . $zipName; // relative to public storage
    }
}
