<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MessageSent;
use App\Services\Notification\NotificationService;

final class SendNewMessageNotification
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function handle(MessageSent $event): void
    {
        $this->notifications->notifyNewMessage($event->message);
    }
}
