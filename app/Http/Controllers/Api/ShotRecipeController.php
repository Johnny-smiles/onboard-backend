<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShotRecipe;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShotRecipeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = ShotRecipe::query()->with('client')->orderBy('name');

        if ($this->isAdmin($user)) {
            if ($request->filled('client_id')) {
                $query->where(function ($scope) use ($request) {
                    $scope->whereNull('client_id')->orWhere('client_id', $request->integer('client_id'));
                });
            }
        } else {
            $query->where(function ($scope) use ($user) {
                $scope->whereNull('client_id');

                if ($user?->client_id) {
                    $scope->orWhere('client_id', $user->client_id);
                }
            });
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureAdmin($request->user());

        $data = $this->validateData($request);

        $recipe = ShotRecipe::create($data);

        return response()->json($recipe->fresh('client'), 201);
    }

    public function show(Request $request, ShotRecipe $shotRecipe): JsonResponse
    {
        $user = $request->user();

        if (!$this->canView($user, $shotRecipe)) {
            abort(403);
        }

        return response()->json($shotRecipe->load('client'));
    }

    public function update(Request $request, ShotRecipe $shotRecipe): JsonResponse
    {
        $this->ensureAdmin($request->user());

        $data = $this->validateData($request);

        $shotRecipe->update($data);

        return response()->json($shotRecipe->fresh('client'));
    }

    public function destroy(Request $request, ShotRecipe $shotRecipe): JsonResponse
    {
        $this->ensureAdmin($request->user());

        $shotRecipe->delete();

        return response()->json(['message' => 'Shot recipe deleted']);
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'steps' => 'required|array|min:1',
            'steps.*.label' => 'required|string|max:255',
            'steps.*.shot_type' => 'required|string|max:255',
            'steps.*.notes' => 'nullable|string',
            'steps.*.job_name' => 'nullable|string|max:255',
        ]);
    }

    protected function ensureAdmin(?User $user): void
    {
        if (!$this->isAdmin($user)) {
            abort(403, 'Admin access required.');
        }
    }

    protected function canView(?User $user, ShotRecipe $recipe): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if ($recipe->client_id === null && $user) {
            return true;
        }

        return $user && $recipe->client_id === $user->client_id;
    }

    protected function isAdmin(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->role === 'admin' || $user->hasRole('admin');
    }
}
