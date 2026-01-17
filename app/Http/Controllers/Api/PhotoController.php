<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\User;
use App\Notifications\NewPhotoUploaded;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
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
            'file' => [
                'required',
                'file',
                'mimes:jpeg,jpg,png,webp,heic', // Specific image types only
                'max:10240', // 10MB max
                'dimensions:max_width=8000,max_height=8000', // Prevent huge images
            ],
            'client_id' => 'nullable|exists:clients,id',
            'project_id' => 'nullable|exists:projects,id',
            'caption' => 'nullable|string|max:2000', // Limit caption length
            'tags' => 'nullable|string|max:500',
            'job_name' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'shot_type' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $clientId = $data['client_id'] ?? $request->user()->client_id;

        if (!$clientId) {
            return response()->json(['message' => 'Client context is required for uploads.'], 422);
        }

        if ($request->user()->role !== 'admin' && $clientId !== $request->user()->client_id) {
            return response()->json(['message' => 'You are not allowed to upload for another client.'], 403);
        }

        $path = $images->optimizeAndStore($request->file('file'), $clientId);

        $photo = Photo::create([
            'user_id' => $request->user()->id,
            'client_id' => $clientId,
            'project_id' => $data['project_id'] ?? null,
            'file_path' => $path,
            'caption' => $data['caption'] ?? null,
            'job_name' => $data['job_name'] ?? null,
            'location' => $data['location'] ?? null,
            'shot_type' => $data['shot_type'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $images->scoreImage($photo);

        if (!empty($data['tags'])) {
            $tags = array_filter(array_map('trim', explode(',', $data['tags'])));

            foreach ($tags as $tag) {
                app(\App\Http\Controllers\Api\PhotoTagController::class)->add($request, $photo->id, $tag);
            }
        }

        Notification::send(User::admins()->get(), new NewPhotoUploaded($photo));

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
