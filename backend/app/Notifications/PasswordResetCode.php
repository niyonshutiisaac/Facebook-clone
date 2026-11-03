<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PasswordResetCode extends Notification
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
            ->subject($this->code.' is your password reset code')
            ->greeting('Hi '.$notifiable->first_name.',')
            ->line('Someone asked us to reset the password for this account. If it was you, enter the code below to continue.')
            ->line('**'.$this->code.'**')
            ->line('This code expires in '.(int) config('auth.passwords.users.expire', 60).' minutes and can only be used once.')
            ->line('If you did not request a reset, you can safely ignore this email — your password will stay as it is.')
            ->salutation('— The '.config('app.name').' team');
    }
}
