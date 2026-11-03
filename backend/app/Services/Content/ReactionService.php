<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Enums\ReactionType;
use App\Models\Comment;
use App\Models\Message;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\Story;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Single entry point for reacting to anything.
 *
 * Reactions are polymorphic, so this service owns the mapping from a string
 * target type to the model class and keeps the notification side effects out of
 * the models themselves.
 */
final class ReactionService
{
    /**
     * @var array<string, class-string<Model>>
     */
    private const array TARGETS = [
        'post' => Post::class,
        'comment' => Comment::class,
        'message' => Message::class,
        'story' => Story::class,
    ];

    public function __construct(private readonly NotificationService $notifications)
    {
    }

    /**
     * Toggle a reaction. Reacting with the type already selected removes it.
     */
    public function toggle(string $targetType, string $targetId, User $user, ReactionType $type): ?Reaction
    {
        $target = $this->resolve($targetType, $targetId);

        if ($target === null) {
            return null;
        }

        /** @var \App\Traits\HasReactions $target */
        $reaction = $target->toggleReaction($user, $type);

        if ($reaction !== null) {
            $this->notifications->notifyReaction($user, $target, $type);
        }

        return $reaction;
    }

    /**
     * @return array<string, int>
     */
    public function summary(string $targetType, string $targetId): array
    {
        $target = $this->resolve($targetType, $targetId);

        return $target === null ? [] : $target->reactionSummary();
    }

    /**
     * Everyone who reacted, optionally filtered to one reaction type.
     *
     * @return Collection<int, Reaction>
     */
    public function reactors(string $targetType, string $targetId, ?ReactionType $type = null, int $limit = 50): Collection
    {
        $target = $this->resolve($targetType, $targetId);

        if ($target === null) {
            return collect();
        }

        $query = $target->reactions()->with('user')->latest();

        if ($type !== null) {
            $query->where('type', $type->value);
        }

        return $query->limit($limit)->get();
    }

    public function resolve(string $targetType, string $targetId): ?Model
    {
        $class = self::TARGETS[$targetType] ?? null;

        if ($class === null) {
            return null;
        }

        return $class::query()->find($targetId);
    }
}
