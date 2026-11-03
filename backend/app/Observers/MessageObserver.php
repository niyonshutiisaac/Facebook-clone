<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Message;

final class MessageObserver
{
    /**
     * Keep the conversation's denormalised "last message" pointers in sync so
     * the inbox stays a single indexed query.
     */
    public function created(Message $message): void
    {
        $conversation = $message->conversation;

        if ($conversation === null) {
            return;
        }

        $conversation->forceFill([
            'last_message_id' => $message->id,
            'last_message_at' => $message->created_at,
        ])->saveQuietly();
    }
}
