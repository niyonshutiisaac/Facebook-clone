<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Offline fallback for chat: only recipients who are not connected to the
 * websocket (or who have mail digests enabled) receive it.
 */
final class NewMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Message $message)
    {
        $this->onQueue('notifications');
    }

    /**
     * @return list<string>
     */
    public function via(\App\Models\User $notifiable): array
    {
        return [$notifiable->notificationPreferences()
            ->where('type', 'message_received')
            ->value('email_enabled') ? 'mail' : 'database'];
    }

    public function toMail(\App\Models\User $notifiable): MailMessage
    {
        $sender = $this->message->sender;

        return (new MailMessage())
            ->subject(($sender?->name() ?? 'Someone').' sent you a message')
            ->greeting('Hi '.$notifiable->first_name.',')
            ->line(\Illuminate\Support\Str::limit($this->message->body ?: 'Sent an attachment', 200))
            ->action('Reply', url('/messages/'.$this->message->conversation_id))
            ->salutation('— The '.config('app.name').' team');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message_id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id' => $this->message->sender_id,
        ];
    }
}
