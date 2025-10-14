<?php
namespace App\Services;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use App\Notifications\ReminderNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
class ReminderService
{
    public function dispatchDueReminders(): int
    {
        $now = Carbon::now();
        $due = Reminder::where('status','pending')->where('schedule_time','<=',$now)->get();
        $count = 0;
        foreach ($due as $reminder) {
            $this->sendReminder($reminder);
            $reminder->update(['status' => 'sent']);
            $this->rescheduleIfRepeating($reminder);
            $count++;
        }
        Log::info("ReminderService dispatched {$count} reminders");
        return $count;
    }
    public function sendReminder(Reminder $reminder): void
    {
        $users = User::where('client_id', $reminder->client_id)->get();
        if ($users->isNotEmpty()) Notification::send($users, new ReminderNotification($reminder));
    }
    protected function rescheduleIfRepeating(Reminder $reminder): void
    {
        $next = null;
        if ($reminder->repeat === 'daily')   $next = now()->parse($reminder->schedule_time)->addDay();
        if ($reminder->repeat === 'weekly')  $next = now()->parse($reminder->schedule_time)->addWeek();
        if ($reminder->repeat === 'monthly') $next = now()->parse($reminder->schedule_time)->addMonth();
        if ($next) $reminder->update(['schedule_time' => $next, 'status' => 'pending']);
    }
}
