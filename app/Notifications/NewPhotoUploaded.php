<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
class NewPhotoUploaded extends Notification implements ShouldQueue
{
    use Queueable;
    public function __construct(public $photo) {}
    public function via($notifiable) { return ['database']; }
    public function toArray($notifiable): array
    {
        return [
            'type' => 'photo_upload',
            'title' => 'New photo uploaded',
            'message' => "A new photo was uploaded by {$this->photo->user->name}",
            'photo_id' => $this->photo->id
        ];
    }
}
