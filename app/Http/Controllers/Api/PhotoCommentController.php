<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\PhotoComment;
use Illuminate\Http\Request;
class PhotoCommentController extends Controller
{
    public function index(Photo $photo) { return response()->json($photo->comments()->with('user:id,name')->latest()->get()); }
    public function store(Request $request, Photo $photo)
    {
        $data = $request->validate(['body'=>'required|string']);
        $c = PhotoComment::create(['photo_id'=>$photo->id, 'user_id'=>$request->user()->id, 'body'=>$data['body']]);
        return response()->json($c->load('user:id,name'), 201);
    }
}
