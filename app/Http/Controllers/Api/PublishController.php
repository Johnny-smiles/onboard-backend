<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\PublishService;
class PublishController extends Controller
{
    public function queueWordpress(Request $request, PublishService $svc)
    {
        $data = $request->validate(['photo_ids'=>'required|array','photo_ids.*'=>'integer','when'=>'nullable|date']);
        $ids = $svc->queue($data['photo_ids'], 'wordpress', $data['when'] ?? null);
        return response()->json(['queued_publications'=>$ids]);
    }
    public function queueMeta(Request $request, PublishService $svc)
    {
        $data = $request->validate(['photo_ids'=>'required|array','photo_ids.*'=>'integer','when'=>'nullable|date']);
        $ids = $svc->queue($data['photo_ids'], 'meta', $data['when'] ?? null);
        return response()->json(['queued_publications'=>$ids]);
    }
    public function queueGBP(Request $request, PublishService $svc)
    {
        $data = $request->validate(['photo_ids'=>'required|array','photo_ids.*'=>'integer','when'=>'nullable|date']);
        $ids = $svc->queue($data['photo_ids'], 'gbp', $data['when'] ?? null);
        return response()->json(['queued_publications'=>$ids]);
    }
    public function processDue(PublishService $svc)
    {
        $n = $svc->dispatchDue();
        return response()->json(['processed'=>$n]);
    }
}
