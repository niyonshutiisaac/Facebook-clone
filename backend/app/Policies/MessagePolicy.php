<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

final class MessagePolicy
{
    /**
     * Anyone in the thread may send; only the sender may edit/unsend.
     */
    public function delete(User $user, Message $message): bool
    {
        return $message->sender_id === $user->id
            || $message->conversation?->participantRecords()
                ->where('user_id', $user->id)
                ->where('is_left', false)
                ->exists();
    }

    public function update(User $user, Message $message): bool
    {
        return $message->sender_id === $user->id;
    }
}
