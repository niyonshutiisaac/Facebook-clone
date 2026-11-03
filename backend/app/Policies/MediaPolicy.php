<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PhotoAlbum;
use App\Models\User;

final class MediaPolicy
{
    public function view(?User $user, PhotoAlbum $album): bool
    {
        if ($album->audience === 'public') {
            return true;
        }

        return $user !== null && ($album->user_id === $user->id || $album->owner?->isFriendWith($user));
    }

    public function update(User $user, PhotoAlbum $album): bool
    {
        return $album->user_id === $user->id;
    }

    public function delete(User $user, PhotoAlbum $album): bool
    {
        return $album->user_id === $user->id;
    }
}
