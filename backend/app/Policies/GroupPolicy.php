<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

final class GroupPolicy
{
    public function view(?User $user, Group $group): bool
    {
        if ($group->isHidden()) {
            return $user !== null
                && $group->membershipRecords()
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->exists();
        }

        return true;
    }

    public function update(User $user, Group $group): bool
    {
        return $group->owner_id === $user->id
            || $group->admins()->where('user_id', $user->id)->where('role', 'admin')->exists();
    }

    public function delete(User $user, Group $group): bool
    {
        return $group->owner_id === $user->id;
    }

    public function moderate(User $user, Group $group): bool
    {
        return $group->isAdmin($user);
    }

    public function post(User $user, Group $group): bool
    {
        return $group->membershipRecords()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }
}
