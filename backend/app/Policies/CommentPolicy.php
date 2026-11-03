<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

final class CommentPolicy
{
    public function update(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->id;
    }

    /**
     * The comment author, the post author and group admins may remove a
     * comment — Facebook grants the same three.
     */
    public function delete(User $user, Comment $comment): bool
    {
        if ($comment->user_id === $user->id) {
            return true;
        }

        $post = $comment->post;

        if ($post !== null && $post->user_id === $user->id) {
            return true;
        }

        return $post?->group?->isAdmin($user) ?? false;
    }
}
