<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Story;
use App\Models\User;

final class StoryPolicy
{
    public function view(User $user, Story $story): bool
    {
        if ($story->user_id === $user->id) {
            return true;
        }

        if ($story->hasExpired()) {
            return false;
        }

        return match ($story->audience) {
            'public', 'friends' => $user->isFriendWith($story->author) || $story->audience === 'public',
            'close_friends' => $story->author?->closeFriends()
                ->where('close_friend_id', $user->id)->exists() ?? false,
            default => false,
        };
    }

    public function delete(User $user, Story $story): bool
    {
        return $story->user_id === $user->id;
    }

    /**
     * Only the author sees who watched their story.
     */
    public function viewInsights(User $user, Story $story): bool
    {
        return $story->user_id === $user->id;
    }
}
