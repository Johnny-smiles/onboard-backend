<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use App\Models\Reminder;
class ReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public function __construct(public Reminder $reminder) {}
    public function via($notifiable) { return ['database']; }
    public function toArray($notifiable): array
    {
        return [
            'type' => 'reminder',
            'title' => 'Photo Reminder',
            'message' => $this->reminder->message,
            'schedule_time' => $this->reminder->schedule_time,
            'client_id' => $this->reminder->client_id,
        ];
    }
}
