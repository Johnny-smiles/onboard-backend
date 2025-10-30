<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\ShotRecipe;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientCaptureController extends Controller
{
    public function recipes(Request $request): JsonResponse
    {
        $clientId = $this->resolveClientId($request);

        $recipes = ShotRecipe::query()
            ->where(function ($query) use ($clientId) {
                $query->whereNull('client_id');

                if ($clientId) {
                    $query->orWhere('client_id', $clientId);
                }
            })
            ->orderBy('client_id')
            ->orderBy('name')
            ->get();

        return response()->json($recipes);
    }

    public function needs(Request $request): JsonResponse
    {
        $clientId = $this->resolveClientId($request);

        $recipes = ShotRecipe::query()
            ->where(function ($query) use ($clientId) {
                $query->whereNull('client_id');

                if ($clientId) {
                    $query->orWhere('client_id', $clientId);
                }
            })
            ->get();

        $photos = Photo::query()
            ->where('client_id', $clientId)
            ->get(['id', 'shot_type', 'job_name']);

        $photosByShotAndJob = $photos->groupBy(function ($photo) {
            return strtolower((string) $photo->shot_type) . '|' . strtolower((string) $photo->job_name);
        });

        $photosByShot = $photos->groupBy(function ($photo) {
            return strtolower((string) $photo->shot_type);
        });

        $payload = $recipes->map(function (ShotRecipe $recipe) use ($photosByShot, $photosByShotAndJob) {
            $steps = collect($recipe->steps ?? []);
            $missing = $steps->filter(function ($step) use ($photosByShot, $photosByShotAndJob) {
                $shotType = strtolower((string) data_get($step, 'shot_type'));
                $jobName = strtolower(trim((string) data_get($step, 'job_name', '')));

                if ($shotType === '') {
                    return false;
                }

                $key = $shotType . '|' . $jobName;

                if ($jobName !== '' && $photosByShotAndJob->has($key) && $photosByShotAndJob->get($key)->isNotEmpty()) {
                    return false;
                }

                if ($jobName === '' && $photosByShot->has($shotType) && $photosByShot->get($shotType)->isNotEmpty()) {
                    return false;
                }

                if ($jobName !== '' && $photosByShot->has($shotType) && $photosByShot->get($shotType)->isNotEmpty()) {
                    return false;
                }

                return true;
            })->values();

            return [
                'recipe_id' => $recipe->id,
                'recipe_name' => $recipe->name,
                'total_steps' => $steps->count(),
                'missing_count' => $missing->count(),
                'missing_steps' => $missing,
            ];
        })->filter(fn ($item) => $item['missing_count'] > 0)->values();

        return response()->json($payload);
    }

    protected function resolveClientId(Request $request): int
    {
        $user = $request->user();

        if ($this->isAdmin($user)) {
            $clientId = (int) $request->input('client_id', 0);

            if ($clientId <= 0) {
                abort(400, 'client_id is required for this request.');
            }

            return $clientId;
        }

        if (!$user?->client_id) {
            abort(403, 'No client associated with this user.');
        }

        return (int) $user->client_id;
    }

    protected function isAdmin(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->role === 'admin' || $user->hasRole('admin');
    }
}
