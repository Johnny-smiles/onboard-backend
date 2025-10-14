<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Photo;
use Illuminate\Http\Request;
use App\Services\ZipService;
class PhotoBulkController extends Controller
{
    public function approve(Request $request)
    {
        $data = $request->validate(['photo_ids' => 'required|array', 'photo_ids.*' => 'integer|exists:photos,id']);
        Photo::whereIn('id', $data['photo_ids'])->update(['approved' => true]);
        return response()->json(['updated' => count($data['photo_ids'])]);
    }
    public function delete(Request $request)
    {
        $data = $request->validate(['photo_ids' => 'required|array', 'photo_ids.*' => 'integer|exists:photos,id']);
        Photo::whereIn('id', $data['photo_ids'])->delete();
        return response()->json(['deleted' => count($data['photo_ids'])]);
    }
    public function export(Request $request, ZipService $zip)
    {
        $data = $request->validate(['photo_ids' => 'required|array', 'photo_ids.*' => 'integer|exists:photos,id']);
        $paths = Photo::whereIn('id', $data['photo_ids'])->pluck('file_path')->all();
        $rel = $zip->createFromPaths($paths, 'photos_export_' . time() . '.zip');
        return response()->json(['url' => url('/storage/' . $rel)]);
    }
}
