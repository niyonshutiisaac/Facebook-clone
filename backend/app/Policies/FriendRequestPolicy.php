<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FriendList;
use App\Models\FriendRequest;
use App\Models\User;

final class FriendRequestPolicy
{
    /**
     * Only the recipient can accept or decline.
     */
    public function respond(User $user, FriendRequest $friendRequest): bool
    {
        return $friendRequest->recipient_id === $user->id;
    }

    /**
     * Only the sender can cancel.
     */
    public function cancel(User $user, FriendRequest $friendRequest): bool
    {
        return $friendRequest->sender_id === $user->id;
    }

    public function view(User $user, FriendRequest $friendRequest): bool
    {
        return $friendRequest->sender_id === $user->id || $friendRequest->recipient_id === $user->id;
    }

    public function updateList(User $user, FriendList $list): bool
    {
        return $list->user_id === $user->id;
    }
}
