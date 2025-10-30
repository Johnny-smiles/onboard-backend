<?php

namespace App\Services;

use App\Models\CaptureReminder;
use Illuminate\Support\Facades\Log;

class CaptureReminderService
{
    public function processDueReminders(): int
    {
        $now = now();
        $processed = 0;

        CaptureReminder::query()
            ->where('is_active', true)
            ->where('send_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(50, function ($reminders) use (&$processed, $now): void {
                foreach ($reminders as $reminder) {
                    $processed++;

                    Log::info('Processing capture reminder (stub)', [
                        'reminder_id' => $reminder->id,
                        'client_id' => $reminder->client_id,
                        'channel' => $reminder->channel,
                        'target' => $reminder->target,
                    ]);

                    $reminder->last_sent_at = $now;
                    $reminder->scheduleNextRun();
                    $reminder->save();
                }
            });

        return $processed;
    }
}
