<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\PhotoPublication;
use App\Models\SocialIntegration;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Get dashboard analytics
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $clientId = $request->query('client_id');

        // Authorize: admin can see all, client can only see their own
        if ($user->role !== 'admin' && $clientId && (int) $clientId !== (int) $user->client_id) {
            abort(403, 'Unauthorized');
        }

        // If client user, force their client_id
        if ($user->role !== 'admin') {
            $clientId = $user->client_id;
        }

        return response()->json([
            'photos' => $this->getPhotoStats($clientId),
            'publications' => $this->getPublicationStats($clientId),
            'integrations' => $this->getIntegrationStats($clientId),
            'activity' => $this->getActivityStats($clientId),
        ]);
    }

    /**
     * Get publishing performance metrics
     */
    public function publishingPerformance(Request $request)
    {
        $user = $request->user();
        $clientId = $request->query('client_id');

        if ($user->role !== 'admin' && $clientId && (int) $clientId !== (int) $user->client_id) {
            abort(403, 'Unauthorized');
        }

        if ($user->role !== 'admin') {
            $clientId = $user->client_id;
        }

        $query = PhotoPublication::query();

        if ($clientId) {
            $query->whereHas('photo', function ($q) use ($clientId) {
                $q->where('client_id', $clientId);
            });
        }

        return response()->json([
            'by_service' => $query->select('service')
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('SUM(CASE WHEN status = "published" THEN 1 ELSE 0 END) as published')
                ->selectRaw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed')
                ->selectRaw('SUM(CASE WHEN status = "queued" THEN 1 ELSE 0 END) as queued')
                ->selectRaw('ROUND(SUM(CASE WHEN status = "published" THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as success_rate')
                ->groupBy('service')
                ->get(),

            'recent_failures' => $query->where('status', 'failed')
                ->with('photo:id,caption,client_id')
                ->latest()
                ->limit(10)
                ->get(),

            'by_day' => $query->select(DB::raw('DATE(created_at) as date'))
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('SUM(CASE WHEN status = "published" THEN 1 ELSE 0 END) as published')
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
        ]);
    }

    private function getPhotoStats($clientId = null): array
    {
        $query = Photo::query();

        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        return [
            'total' => $query->count(),
            'pending_review' => (clone $query)->where('approved', false)->count(),
            'approved' => (clone $query)->where('approved', true)->count(),
            'uploaded_today' => (clone $query)->whereDate('created_at', today())->count(),
            'uploaded_this_week' => (clone $query)->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'uploaded_this_month' => (clone $query)->whereMonth('created_at', now()->month)->count(),

            'by_shot_type' => (clone $query)->select('shot_type')
                ->selectRaw('COUNT(*) as count')
                ->whereNotNull('shot_type')
                ->groupBy('shot_type')
                ->get(),
        ];
    }

    private function getPublicationStats($clientId = null): array
    {
        $query = PhotoPublication::query();

        if ($clientId) {
            $query->whereHas('photo', function ($q) use ($clientId) {
                $q->where('client_id', $clientId);
            });
        }

        $total = $query->count();
        $published = (clone $query)->where('status', 'published')->count();

        return [
            'total' => $total,
            'published' => $published,
            'queued' => (clone $query)->where('status', 'queued')->count(),
            'failed' => (clone $query)->where('status', 'failed')->count(),
            'success_rate' => $total > 0 ? round(($published / $total) * 100, 2) : 0,
            'published_today' => (clone $query)->where('status', 'published')
                ->whereDate('published_at', today())->count(),
            'published_this_week' => (clone $query)->where('status', 'published')
                ->whereBetween('published_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),

            'by_service' => (clone $query)->select('service')
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('SUM(CASE WHEN status = "published" THEN 1 ELSE 0 END) as published')
                ->groupBy('service')
                ->get(),
        ];
    }

    private function getIntegrationStats($clientId = null): array
    {
        $query = SocialIntegration::query();

        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        return [
            'total' => $query->count(),
            'active' => (clone $query)->where('status', 'active')->count(),
            'error' => (clone $query)->where('status', 'error')->count(),
            'expiring_soon' => (clone $query)->where('status', 'active')
                ->where('expires_at', '<=', now()->addDays(7))
                ->count(),

            'by_provider' => (clone $query)->select('provider')
                ->selectRaw('COUNT(*) as count')
                ->selectRaw('SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active')
                ->groupBy('provider')
                ->get(),
        ];
    }

    private function getActivityStats($clientId = null): array
    {
        $userQuery = User::query();

        if ($clientId) {
            $userQuery->where('client_id', $clientId);
        }

        return [
            'users' => $userQuery->count(),
            'active_users_today' => (clone $userQuery)->whereDate('updated_at', today())->count(),

            'recent_uploads' => Photo::query()
                ->when($clientId, fn($q) => $q->where('client_id', $clientId))
                ->with('user:id,name', 'client:id,name')
                ->latest()
                ->limit(5)
                ->get(['id', 'caption', 'created_at', 'user_id', 'client_id', 'approved']),
        ];
    }
}
