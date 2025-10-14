<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class PhotoTagController extends Controller
{
    public function add(Request $request, int $photoId, ?string $tagName = null)
    {
        $photo = Photo::findOrFail($photoId);
        $name = $tagName ?? $request->validate(['tag'=>'required|string'])['tag'];
        $slug = Str::slug($name);
        $tag = Tag::firstOrCreate(['slug'=>$slug], ['name'=>$name]);
        $photo->tags()->syncWithoutDetaching([$tag->id]);
        return response()->json(['ok'=>true]);
    }
    public function remove(Request $request, int $photoId)
    {
        $data = $request->validate(['tag'=>'required|string']);
        $photo = Photo::findOrFail($photoId);
        $tag = Tag::where('slug', Str::slug($data['tag']))->orWhere('name',$data['tag'])->first();
        if ($tag) $photo->tags()->detach($tag->id);
        return response()->json(['ok'=>true]);
    }
}
