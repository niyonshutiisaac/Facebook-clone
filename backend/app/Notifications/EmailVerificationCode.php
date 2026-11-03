<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class EmailVerificationCode extends Notification
{
    use Queueable;

    public function __construct(public readonly string $code)
    {
        $this->onQueue('notifications');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(\App\Models\User $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject($this->code.' is your confirmation code')
            ->greeting('Welcome to '.config('app.name').'!')
            ->line('Confirm '.e($notifiable->email).' to finish setting up your account.')
            ->line('**'.$this->code.'**')
            ->line('The code expires in 15 minutes.')
            ->salutation('— The '.config('app.name').' team');
    }
}
