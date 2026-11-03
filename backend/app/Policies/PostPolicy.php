<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use App\Services\Social\PrivacyService;

final class PostPolicy
{
    public function __construct(private readonly PrivacyService $privacy)
    {
    }

    public function view(?User $user, Post $post): bool
    {
        if ($post->user_id === $user?->id) {
            return true;
        }

        return $this->privacy->canSee(
            $user,
            \App\Enums\Audience::tryFrom((string) $post->audience) ?? \App\Enums\Audience::Public,
            (string) $post->id,
            'post',
            (string) $post->user_id
        );
    }

    public function create(User $user): bool
    {
        return $user->canAuthenticate();
    }

    public function update(User $user, Post $post): bool
    {
        return $post->user_id === $user->id;
    }

    public function delete(User $user, Post $post): bool
    {
        if ($post->user_id === $user->id) {
            return true;
        }

        // Group and page admins may remove content posted inside their space.
        if ($post->group_id !== null) {
            return $post->group?->isAdmin($user) ?? false;
        }

        if ($post->page_id !== null) {
            return $post->page?->roles()
                ->where('user_id', $user->id)
                ->whereIn('role', ['admin', 'editor', 'moderator'])
                ->exists() ?? false;
        }

        return false;
    }

    public function comment(User $user, Post $post): bool
    {
        return $this->view($user, $post) && (bool) $post->comments_enabled;
    }
}
