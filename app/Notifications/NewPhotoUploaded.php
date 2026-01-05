<?php

namespace App\Notifications;

use App\Models\Photo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewPhotoUploaded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Photo $photo)
    {
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $client = $this->photo->client;
        $uploader = $this->photo->user;

        return (new MailMessage())
            ->subject('New Photo Uploaded - Needs Review')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('A new photo has been uploaded and is waiting for your review.')
            ->line('**Client:** '.$client->name)
            ->line('**Uploaded by:** '.$uploader->name)
            ->line('**Caption:** '.($this->photo->caption ?? 'No caption provided'))
            ->action('Review Photo', url('/api/v1/photos/'.$this->photo->id))
            ->line('Please review and approve the photo as soon as possible.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'photo_upload',
            'title' => 'New photo uploaded',
            'message' => "A new photo was uploaded by {$this->photo->user->name}",
            'photo_id' => $this->photo->id,
            'client_id' => $this->photo->client_id,
        ];
    }
}
