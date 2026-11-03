<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Comment;

final class CommentObserver
{
    public function created(Comment $comment): void
    {
        $comment->post?->recountComments();

        if ($comment->parent_id !== null) {
            $comment->parent?->recountReplies();
        }
    }

    public function deleted(Comment $comment): void
    {
        $comment->post?->recountComments();
        $comment->parent?->recountReplies();
    }

    /**
     * Deleting a comment must not leave orphaned replies in the thread, just as
     * deleting a post must not leave orphaned comments.
     */
    public function deleting(Comment $comment): void
    {
        $comment->replies()->each(fn (Comment $reply): mixed => $reply->delete());
    }
}
