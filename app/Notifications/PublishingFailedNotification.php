<?php

namespace App\Notifications;

use App\Models\PhotoPublication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PublishingFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public PhotoPublication $publication)
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
        $photo = $this->publication->photo;
        $service = ucfirst($this->publication->service);

        return (new MailMessage())
            ->error()
            ->subject('Publishing Failed - '.$service)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('A photo failed to publish to '.$service.'.')
            ->line('**Error:** '.$this->publication->error)
            ->line('**Photo Caption:** '.($photo->caption ?? 'No caption'))
            ->line('**Service:** '.$service)
            ->action('View Photo', url('/api/v1/photos/'.$photo->id))
            ->line('Please check your '.$service.' integration settings and try again.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'publishing_failed',
            'title' => 'Publishing failed',
            'message' => "Failed to publish to {$this->publication->service}",
            'photo_id' => $this->publication->photo_id,
            'publication_id' => $this->publication->id,
            'service' => $this->publication->service,
            'error' => $this->publication->error,
        ];
    }
}
