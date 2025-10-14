<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Photo;
use Illuminate\Http\Request;
class CaptionController extends Controller
{
    public function suggest(Photo $photo)
    {
        // Simple deterministic stub caption
        $caption = "On Brand: Client {$photo->client_id} — captured " . $photo->created_at->format('M d, Y') . ".";
        return response()->json(['caption'=>$caption]);
    }
}
