<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaptureReminder;
use App\Models\User;
use App\Services\CaptureReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CaptureReminderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = CaptureReminder::query()->with(['client:id,name', 'shotRecipe:id,name']);

        if ($this->isAdmin($user)) {
            if ($request->filled('client_id')) {
                $query->where('client_id', $request->integer('client_id'));
            }
        } else {
            $query->where('client_id', $user->client_id ?? 0);
        }

        return response()->json($query->orderByDesc('send_at')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureAdmin($request->user());

        $data = $this->validateData($request);

        $reminder = CaptureReminder::create($data);

        return response()->json($reminder->fresh(['client:id,name', 'shotRecipe:id,name']), 201);
    }

    public function show(Request $request, CaptureReminder $captureReminder): JsonResponse
    {
        if (!$this->canAccess($request->user(), $captureReminder)) {
            abort(403);
        }

        return response()->json($captureReminder->load(['client:id,name', 'shotRecipe:id,name']));
    }

    public function update(Request $request, CaptureReminder $captureReminder): JsonResponse
    {
        $this->ensureAdmin($request->user());

        $data = $this->validateData($request);

        $captureReminder->update($data);

        return response()->json($captureReminder->fresh(['client:id,name', 'shotRecipe:id,name']));
    }

    public function destroy(Request $request, CaptureReminder $captureReminder): JsonResponse
    {
        $this->ensureAdmin($request->user());

        $captureReminder->delete();

        return response()->json(['message' => 'Capture reminder deleted']);
    }

    public function runDue(Request $request, CaptureReminderService $service): JsonResponse
    {
        $this->ensureAdmin($request->user());

        $processed = $service->processDueReminders();

        return response()->json(['processed' => $processed]);
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'client_id' => 'required|exists:clients,id',
            'shot_recipe_id' => 'nullable|exists:shot_recipes,id',
            'title' => 'required|string|max:255',
            'message' => 'nullable|string',
            'channel' => 'required|string|in:email,sms',
            'target' => 'nullable|string|max:255',
            'send_at' => 'required|date',
            'repeat_interval' => 'nullable|string|in:daily,weekly,monthly',
            'is_active' => 'boolean',
        ]);
    }

    protected function canAccess(?User $user, CaptureReminder $reminder): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        return $user && $user->client_id === $reminder->client_id;
    }

    protected function ensureAdmin(?User $user): void
    {
        if (!$this->isAdmin($user)) {
            abort(403, 'Admin access required.');
        }
    }

    protected function isAdmin(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->role === 'admin' || $user->hasRole('admin');
    }
}
