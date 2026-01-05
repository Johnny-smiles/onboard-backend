<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PhotoPublication;
use App\Services\PublishService;
use Illuminate\Http\Request;

class PublishController extends Controller
{
    /**
     * Generic queue endpoint - supports all services
     */
    public function queue(Request $request, PublishService $svc)
    {
        $data = $request->validate([
            'photo_ids' => 'required|array',
            'photo_ids.*' => 'integer|exists:photos,id',
            'service' => 'required|in:wordpress,meta,gbp,google',
            'scheduled_at' => 'nullable|date|after:now',
            'client_id' => 'nullable|exists:clients,id',
        ]);

        $ids = $svc->queue(
            $data['photo_ids'],
            $data['service'],
            $data['scheduled_at'] ?? null,
            $data['client_id'] ?? null
        );

        return response()->json([
            'queued_publications' => $ids,
            'scheduled_at' => $data['scheduled_at'] ?? 'now',
        ]);
    }

    public function queueWordpress(Request $request, PublishService $svc)
    {
        $data = $request->validate([
            'photo_ids' => 'required|array',
            'photo_ids.*' => 'integer',
            'when' => 'nullable|date',
        ]);
        $ids = $svc->queue($data['photo_ids'], 'wordpress', $data['when'] ?? null);

        return response()->json(['queued_publications' => $ids]);
    }

    public function queueMeta(Request $request, PublishService $svc)
    {
        $data = $request->validate([
            'photo_ids' => 'required|array',
            'photo_ids.*' => 'integer',
            'when' => 'nullable|date',
        ]);
        $ids = $svc->queue($data['photo_ids'], 'meta', $data['when'] ?? null);

        return response()->json(['queued_publications' => $ids]);
    }

    public function queueGBP(Request $request, PublishService $svc)
    {
        $data = $request->validate([
            'photo_ids' => 'required|array',
            'photo_ids.*' => 'integer',
            'when' => 'nullable|date',
        ]);
        $ids = $svc->queue($data['photo_ids'], 'gbp', $data['when'] ?? null);

        return response()->json(['queued_publications' => $ids]);
    }

    /**
     * Get scheduled publications (calendar view)
     */
    public function scheduled(Request $request)
    {
        $query = PhotoPublication::with(['photo'])
            ->where('status', 'queued')
            ->whereNotNull('scheduled_at');

        // Filter by client if not admin
        if ($request->user()->role !== 'admin') {
            $query->whereHas('photo', function ($q) use ($request) {
                $q->where('client_id', $request->user()->client_id);
            });
        }

        // Filter by client_id if provided
        if ($request->has('client_id')) {
            $query->whereHas('photo', function ($q) use ($request) {
                $q->where('client_id', $request->client_id);
            });
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('scheduled_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('scheduled_at', '<=', $request->end_date);
        }

        // Filter by service
        if ($request->has('service')) {
            $query->where('service', $request->service);
        }

        $publications = $query->orderBy('scheduled_at')->get();

        return response()->json([
            'scheduled' => $publications,
            'total' => $publications->count(),
        ]);
    }

    /**
     * Update scheduled time for a publication
     */
    public function reschedule(Request $request, PhotoPublication $publication)
    {
        $data = $request->validate([
            'scheduled_at' => 'required|date|after:now',
        ]);

        // Ensure user has access to this publication
        if ($request->user()->role !== 'admin') {
            if ($publication->photo->client_id !== $request->user()->client_id) {
                abort(403, 'Unauthorized');
            }
        }

        // Only allow rescheduling if still queued
        if ($publication->status !== 'queued') {
            return response()->json([
                'message' => 'Can only reschedule queued publications',
            ], 400);
        }

        $publication->update([
            'scheduled_at' => $data['scheduled_at'],
        ]);

        return response()->json([
            'message' => 'Publication rescheduled',
            'publication' => $publication,
        ]);
    }

    /**
     * Cancel a scheduled publication
     */
    public function cancel(Request $request, PhotoPublication $publication)
    {
        // Ensure user has access to this publication
        if ($request->user()->role !== 'admin') {
            if ($publication->photo->client_id !== $request->user()->client_id) {
                abort(403, 'Unauthorized');
            }
        }

        // Only allow canceling if still queued
        if ($publication->status !== 'queued') {
            return response()->json([
                'message' => 'Can only cancel queued publications',
            ], 400);
        }

        $publication->delete();

        return response()->json([
            'message' => 'Publication canceled',
        ]);
    }

    public function processDue(PublishService $svc)
    {
        $n = $svc->dispatchDue();

        return response()->json(['processed' => $n]);
    }
}

