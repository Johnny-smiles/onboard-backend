<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\User;
use App\Notifications\NewPhotoUploaded;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PhotoController extends Controller
{
    public function index(Request $request)
    {
        $photos = QueryBuilder::for(
            Photo::query()->with(['user', 'client', 'tags'])
        )
            ->allowedFilters([
                AllowedFilter::exact('client_id'),
                AllowedFilter::callback('approved', function ($query, $value) {
                    $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                    if ($normalized !== null) {
                        $query->where('approved', $normalized);
                    }
                }),
                AllowedFilter::callback('tag', function ($query, $value) {
                    $tags = collect($value)->filter()->flatMap(function ($item) {
                        return array_map('trim', explode(',', (string) $item));
                    })->filter();

                    if ($tags->isNotEmpty()) {
                        $query->whereHas('tags', function ($relation) use ($tags) {
                            $relation->where(function ($tagQuery) use ($tags) {
                                foreach ($tags as $tag) {
                                    $tagQuery->orWhere('slug', $tag)->orWhere('name', $tag);
                                }
                            });
                        });
                    }
                }),
            ])
            ->allowedSorts(['created_at', 'quality_score'])
            ->defaultSort('-created_at')
            ->paginate(25)
            ->appends($request->query());

        return response()->json($photos);
    }

    public function store(Request $request, ImageService $images)
    {
        $data = $request->validate([
            'file' => 'required|image|max:8192',
            'client_id' => 'required|exists:clients,id',
            'project_id' => 'nullable|exists:projects,id',
            'caption' => 'nullable|string',
            'tags' => 'nullable|string',
        ]);

        $path = $images->optimizeAndStore($request->file('file'), $data['client_id']);

        $photo = Photo::create([
            'user_id' => $request->user()->id,
            'client_id' => $data['client_id'],
            'project_id' => $data['project_id'] ?? null,
            'file_path' => $path,
            'caption' => $data['caption'] ?? null,
        ]);

        $images->scoreImage($photo);

        if (!empty($data['tags'])) {
            $tags = array_filter(array_map('trim', explode(',', $data['tags'])));

            foreach ($tags as $tag) {
                app(\App\Http\Controllers\Api\PhotoTagController::class)->add($request, $photo->id, $tag);
            }
        }

        User::admins()->get()->each(fn ($admin) => $admin->notify(new NewPhotoUploaded($photo)));

        return response()->json($photo, 201);
    }

    public function approve(Photo $photo)
    {
        $photo->update(['approved' => true]);

        return response()->json(['message' => 'Photo approved']);
    }

    public function destroy(Photo $photo)
    {
        Storage::disk(config('filesystems.default'))->delete($photo->file_path);
        $photo->delete();

        return response()->json(['message' => 'Photo deleted']);
    }
}
