<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Comment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Comment thread helpers for any model that owns comments through a plain
 * foreign key (`comments.post_id`).
 *
 * Comments are threads rather than a flat list: a reply is a comment whose
 * `parent_id` is set, so "load the thread" is one indexed query on
 * (post_id, parent_id, created_at) plus one for replies.
 *
 * @mixin Model
 */
trait HasComments
{
    /**
     * Top level comments only — replies hang off each comment via `replies()`.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->whereNull('parent_id');
    }

    /**
     * Every comment on the target, including replies. Used for counters and
     * moderation, never for rendering (which needs the tree shape).
     */
    public function allComments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function recountComments(): void
    {
        if (! array_key_exists('comments_count', $this->getAttributes())) {
            return;
        }

        $this->forceFill([
            'comments_count' => $this->allComments()->count(),
        ])->saveQuietly();
    }
}
