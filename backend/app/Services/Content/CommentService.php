<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Threaded comments: create, edit, delete, reply and paginate.
 *
 * Nesting is capped at three levels (Facebook collapses anything deeper into a
 * flat "continue thread" link), which keeps the recursive UI bounded while
 * still feeling like a conversation.
 */
final class CommentService
{
    public const int MAX_DEPTH = 3;

    public function __construct(private readonly NotificationService $notifications)
    {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $author, Post $post, array $attributes): Comment
    {
        if (! $post->comments_enabled) {
            abort(422, 'Comments are disabled on this post.');
        }

        return DB::transaction(function () use ($author, $post, $attributes): Comment {
            $parent = isset($attributes['parent_id'])
                ? Comment::query()->where('post_id', $post->id)->find($attributes['parent_id'])
                : null;

            /** @var Comment $comment */
            $comment = Comment::create([
                'post_id' => $post->id,
                'user_id' => $author->id,
                'parent_id' => $parent?->id,
                'depth' => $parent === null ? 0 : min(self::MAX_DEPTH, $parent->depth + 1),
                'path' => $parent === null ? null : ltrim(($parent->path ?? '').'/'.$parent->id, '/'),
                'body' => (string) $attributes['body'],
            ]);

            $post->recountComments();

            if ($parent !== null) {
                $parent->recountReplies();
            }

            $this->notifications->notifyComment($author, $comment, $parent);

            return $comment->fresh(['author']);
        });
    }

    public function update(Comment $comment, string $body): Comment
    {
        $comment->forceFill([
            'body' => $body,
            'is_edited' => true,
        ])->save();

        return $comment->fresh(['author']);
    }

    /**
     * Soft delete but keep the thread readable: a deleted comment with replies
     * becomes a tombstone so answers below it still make sense.
     */
    public function delete(Comment $comment): void
    {
        DB::transaction(function () use ($comment): void {
            $post = $comment->post;
            $parent = $comment->parent;

            if ($comment->replies()->exists()) {
                $comment->forceFill(['body' => '', 'is_hidden' => true])->save();
                $comment->delete();
            } else {
                $comment->delete();
            }

            $parent?->recountReplies();
            $post?->recountComments();
        });
    }

    /**
     * Top level comments for a post, newest first, with the viewer's own
     * reaction preloaded.
     *
     * @return LengthAwarePaginator<Comment>
     */
    public function thread(Post $post, int $perPage = 20, int $page = 1): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<Comment> $paginator */
        $paginator = $post->comments()
            ->with(['author', 'media', 'reactions'])
            ->withCount('replies')
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return $paginator;
    }

    /**
     * Replies to a comment, oldest first so a thread reads top to bottom.
     *
     * @return Collection<int, Comment>
     */
    public function replies(Comment $comment, int $limit = 50): Collection
    {
        return $comment->replies()
            ->with(['author', 'media', 'reactions'])
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }
}
