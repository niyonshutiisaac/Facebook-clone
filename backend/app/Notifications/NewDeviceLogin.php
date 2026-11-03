<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class NewDeviceLogin extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $ipAddress,
        public readonly string $userAgent,
    ) {
        $this->onQueue('notifications');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(\App\Models\User $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('New sign-in to your account')
            ->greeting('Hi '.$notifiable->first_name.',')
            ->line('We noticed a sign-in to your account from a device we do not recognise.')
            ->line('**Where:** '.$this->userAgent)
            ->line('**IP address:** '.$this->ipAddress)
            ->line('**When:** '.now()->toDayDateTimeString())
            ->line('If this was you, nothing else is needed. If it was not, secure your account right away.')
            ->action('Review your sessions', url('/settings/security'))
            ->salutation('— The '.config('app.name').' team');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'signed_in_at' => now()->toIso8601String(),
        ];
    }
}
