<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhotoReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Photo::query()->with(['client', 'user'])->latest();

        if (!$this->isAdmin($user)) {
            $query->where('client_id', $user->client_id);
        } elseif ($request->filled('client_id')) {
            $query->where('client_id', $request->input('client_id'));
        }

        $status = $request->input('status');

        if ($status) {
            $query->where('review_status', $status);
        } else {
            $query->where('review_status', 'pending');
        }

        if ($request->filled('shot_type')) {
            $query->where('shot_type', $request->input('shot_type'));
        }

        $photos = $query->paginate(25)->appends($request->query());

        return response()->json($photos);
    }

    public function approve(Request $request, Photo $photo): JsonResponse
    {
        $user = $request->user();
        $this->ensureCanAccessPhoto($user, $photo);

        $photo->forceFill([
            'review_status' => 'approved',
            'review_notes' => null,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Photo approved',
            'photo' => $photo->fresh(),
        ]);
    }

    public function reject(Request $request, Photo $photo): JsonResponse
    {
        $user = $request->user();
        $this->ensureCanAccessPhoto($user, $photo);

        $data = $request->validate([
            'reason' => 'required|string|max:5000',
        ]);

        $photo->forceFill([
            'review_status' => 'rejected',
            'review_notes' => $data['reason'],
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Photo rejected',
            'photo' => $photo->fresh(),
        ]);
    }

    protected function isAdmin(User $user): bool
    {
        return $user->role === 'admin' || $user->hasRole('admin');
    }

    protected function ensureCanAccessPhoto(User $user, Photo $photo): void
    {
        if ($this->isAdmin($user)) {
            return;
        }

        if ($user->client_id !== $photo->client_id) {
            abort(403, 'You are not authorized to review this photo.');
        }
    }
}
