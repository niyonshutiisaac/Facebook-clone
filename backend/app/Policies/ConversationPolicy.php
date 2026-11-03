<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

final class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->participantRecords()
            ->where('user_id', $user->id)
            ->where('is_left', false)
            ->exists();
    }

    public function update(User $user, Conversation $conversation): bool
    {
        return $conversation->type === 'group'
            && $conversation->participantRecords()
                ->where('user_id', $user->id)
                ->where('role', 'admin')
                ->exists();
    }

    public function addParticipants(User $user, Conversation $conversation): bool
    {
        return $this->update($user, $conversation);
    }

    public function delete(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }
}
