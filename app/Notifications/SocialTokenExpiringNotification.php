<?php

namespace App\Notifications;

use App\Models\SocialIntegration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SocialTokenExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SocialIntegration $integration)
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
        $provider = ucfirst($this->integration->provider);
        $client = $this->integration->client;
        $daysUntilExpiry = now()->diffInDays($this->integration->expires_at, false);

        $urgency = $daysUntilExpiry <= 1 ? 'URGENT' : 'Warning';

        return (new MailMessage())
            ->subject($urgency.': '.$provider.' Token Expiring Soon')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('A social media integration token is expiring soon and needs to be renewed.')
            ->line('**Client:** '.$client->name)
            ->line('**Platform:** '.$provider)
            ->line('**Account:** '.$this->integration->account_name)
            ->line('**Expires:** '.$this->integration->expires_at->diffForHumans())
            ->action('Reconnect '.$provider, url('/api/v1/clients/'.$client->id.'/integrations'))
            ->line('Publishing to this platform will fail after the token expires.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'token_expiring',
            'title' => 'Social token expiring',
            'message' => "{$this->integration->provider} token expires {$this->integration->expires_at->diffForHumans()}",
            'integration_id' => $this->integration->id,
            'client_id' => $this->integration->client_id,
            'provider' => $this->integration->provider,
            'expires_at' => $this->integration->expires_at->toIso8601String(),
        ];
    }
}
