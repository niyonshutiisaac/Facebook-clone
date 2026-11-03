<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

final class EventPolicy
{
    public function view(?User $user, Event $event): bool
    {
        return $event->privacy === 'public' || $user !== null;
    }

    public function update(User $user, Event $event): bool
    {
        return $event->user_id === $user->id
            || ($event->group !== null && $event->group->isAdmin($user));
    }

    public function delete(User $user, Event $event): bool
    {
        return $event->user_id === $user->id;
    }
}
