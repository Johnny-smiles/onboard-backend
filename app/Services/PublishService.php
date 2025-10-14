<?php
namespace App\Services;
use App\Models\PhotoPublication;
use Carbon\Carbon;
class PublishService
{
    // Stub: enqueue publications, processed later by scheduler
    public function queue(array $photoIds, string $service, ?string $when = null): array
    {
        $scheduled = $when ? Carbon::parse($when) : now();
        $ids = [];
        foreach ($photoIds as $pid) {
            $pub = PhotoPublication::create([
                'photo_id' => $pid,
                'service' => $service,
                'status' => 'queued',
                'scheduled_at' => $scheduled,
                'payload' => ['note' => 'stub: will publish at scheduled time'],
            ]);
            $ids[] = $pub->id;
        }
        return $ids;
    }
    // Called by scheduler to process queued publications
    public function dispatchDue(): int
    {
        $due = PhotoPublication::where('status','queued')->where('scheduled_at','<=', now())->get();
        $count = 0;
        foreach ($due as $pub) {
            // Simulate success
            $pub->update([
                'status' => 'published',
                'published_at' => now(),
                'payload' => array_merge($pub->payload ?? [], ['external_id' => 'stub-' . $pub->id])
            ]);
            $count++;
        }
        return $count;
    }
}
