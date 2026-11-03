<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Enums\NotificationType;
use App\Enums\ReactionType;
use App\Events\NewNotification;
use App\Models\Comment;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Writes, aggregates and delivers notifications.
 *
 * Aggregation is the interesting part: five people liking the same post inside
 * the window must surface as one row ("Ava and 4 others liked your photo"),
 * not five. Rows are keyed by (recipient, type, subject) and the actor is the
 * most recent person to act, which is exactly how Facebook renders the bell.
 */
final class NotificationService
{
    /** Notifications of the same type on the same subject collapse for this long. */
    private const int AGGREGATION_WINDOW_MINUTES = 60;

    public function __construct()
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function notify(
        User $recipient,
        NotificationType $type,
        ?User $actor,
        ?Model $subject = null,
        array $data = [],
        ?string $body = null,
    ): ?Notification {
        // Never notify about your own actions.
        if ($actor !== null && $actor->id === $recipient->id) {
            return null;
        }

        if (! $this->isEnabled($recipient, $type, 'in_app_enabled')) {
            return null;
        }

        if ($actor !== null && $this->isBlocked($recipient, $actor)) {
            return null;
        }

        return DB::transaction(function () use ($recipient, $type, $actor, $subject, $data, $body): Notification {
            $existing = $this->findAggregatable($recipient, $type, $subject);

            if ($existing !== null && $actor !== null) {
                $existing->increment('aggregated_count');
                $existing->forceFill([
                    'actor_id' => $actor->id,
                    'read_at' => null,
                    'created_at' => now(),
                    'data' => array_merge((array) $existing->data, $data),
                ])->save();

                $notification = $existing->fresh(['actor']);
            } else {
                /** @var Notification $notification */
                $notification = Notification::create([
                    'user_id' => $recipient->id,
                    'actor_id' => $actor?->id,
                    'type' => $type->value,
                    'notifiable_type' => $subject !== null ? $this->morphAlias($subject) : null,
                    'notifiable_id' => $subject?->getKey(),
                    'data' => $data !== [] ? $data : null,
                    'body' => $body ?? $this->renderBody($type, $actor, $data),
                ]);

                $notification->load('actor');
            }

            broadcast(new NewNotification($notification))->toOthers();

            return $notification;
        });
    }

    public function notifyReaction(User $actor, Model $target, ReactionType $reaction): ?Notification
    {
        $recipient = $this->ownerOf($target);

        if ($recipient === null) {
            return null;
        }

        $type = $target instanceof Comment
            ? NotificationType::CommentReacted
            : NotificationType::PostReacted;

        return $this->notify($recipient, $type, $actor, $target, [
            'reaction' => $reaction->value,
            'emoji' => $reaction->emoji(),
            'preview' => $this->excerptOf($target),
        ]);
    }

    public function notifyComment(User $actor, Comment $comment, ?Comment $parent = null): ?Notification
    {
        $post = $comment->post;

        if ($post === null) {
            return null;
        }

        // Replying to a comment notifies the parent's author; a top level
        // comment notifies the post's author.
        $recipient = $parent?->author ?? $post->author;

        $type = $parent !== null
            ? NotificationType::CommentReplied
            : NotificationType::PostCommented;

        return $this->notify($recipient, $type, $actor, $post, [
            'comment_id' => $comment->id,
            'preview' => \Illuminate\Support\Str::limit($comment->body, 120),
        ]);
    }

    public function notifyNewMessage(Message $message): void
    {
        $sender = $message->sender;

        foreach ($message->conversation->participantRecords as $participant) {
            if ($participant->user_id === $sender->id || $participant->is_left || $participant->is_muted) {
                continue;
            }

            $recipient = $participant->user;

            $this->notify($recipient, NotificationType::NewMessage, $sender, $message->conversation, [
                'conversation_id' => $message->conversation_id,
                'message_id' => $message->id,
                'preview' => \Illuminate\Support\Str::limit((string) $message->body, 120),
            ]);
        }
    }

    public function notifyFriendRequest(\App\Models\FriendRequest $request): ?Notification
    {
        return $this->notify(
            $request->recipient,
            NotificationType::FriendRequestReceived,
            $request->sender,
            $request,
            ['request_id' => $request->id]
        );
    }

    public function notifyFriendRequestAccepted(\App\Models\FriendRequest $request): ?Notification
    {
        return $this->notify(
            $request->sender,
            NotificationType::FriendRequestAccepted,
            $request->recipient,
            null,
            ['user_id' => $request->recipient_id]
        );
    }

    // ----------------------------------------------------------------- reads

    /**
     * @return LengthAwarePaginator<Notification>
     */
    public function feed(User $user, bool $unreadOnly = false, int $perPage = 20): LengthAwarePaginator
    {
        $query = Notification::query()
            ->forUser($user)
            ->with(['actor', 'notifiable'])
            ->latest();

        if ($unreadOnly) {
            $query->unread();
        }

        /** @var LengthAwarePaginator<Notification> $page */
        $page = $query->paginate($perPage);

        return $page;
    }

    public function unreadCount(User $user): int
    {
        return Notification::query()->forUser($user)->unread()->count();
    }

    public function markRead(User $user, ?string $notificationId = null): int
    {
        if ($notificationId !== null) {
            $notification = Notification::query()
                ->forUser($user)
                ->where('id', $notificationId)
                ->firstOrFail();

            if (! $notification->isRead()) {
                $notification->markAsRead();

                return 1;
            }

            return 0;
        }

        return Notification::query()->forUser($user)->unread()->update(['read_at' => now()]);
    }

    // ------------------------------------------------------------ preferences

    public function isEnabled(User $user, NotificationType $type, string $channel): bool
    {
        $preference = $user->notificationPreferences()
            ->whereIn('type', [$type->value, '*'])
            ->get()
            ->sortByDesc(fn ($p): int => $p->type === '*' ? 0 : 1)
            ->first();

        if ($preference === null) {
            return true;
        }

        if ($channel === 'email_enabled' && $preference->email_frequency === 'off') {
            return false;
        }

        return (bool) $preference->{$channel};
    }

    // ------------------------------------------------------------- internals

    private function findAggregatable(User $recipient, NotificationType $type, ?Model $subject): ?Notification
    {
        if ($subject === null) {
            return null;
        }

        return Notification::query()
            ->where('user_id', $recipient->id)
            ->where('type', $type->value)
            ->where('notifiable_type', $this->morphAlias($subject))
            ->where('notifiable_id', $subject->getKey())
            ->where('created_at', '>=', now()->subMinutes(self::AGGREGATION_WINDOW_MINUTES))
            ->first();
    }

    private function ownerOf(Model $target): ?User
    {
        return match (true) {
            $target instanceof Post => $target->author,
            $target instanceof Comment => $target->author,
            default => method_exists($target, 'user') ? $target->user : null,
        };
    }

    private function excerptOf(Model $target): string
    {
        $text = match (true) {
            $target instanceof Post => (string) $target->body,
            $target instanceof Comment => (string) $target->body,
            default => '',
        };

        return \Illuminate\Support\Str::limit($text, 80);
    }

    private function morphAlias(Model $model): string
    {
        return $model->getMorphClass();
    }

    /**
     * Human readable fallback text, used by email and push payloads where the
     * rich UI renderer is unavailable.
     *
     * @param  array<string, mixed>  $data
     */
    private function renderBody(NotificationType $type, ?User $actor, array $data): string
    {
        $name = $actor?->name() ?? 'Someone';

        return match ($type) {
            NotificationType::FriendRequestReceived => sprintf('%s sent you a friend request.', $name),
            NotificationType::FriendRequestAccepted => sprintf('%s accepted your friend request.', $name),
            NotificationType::PostReacted, NotificationType::CommentReacted => sprintf(
                '%s reacted %s to your post.', $name, (string) ($data['emoji'] ?? '👍')
            ),
            NotificationType::PostCommented => sprintf('%s commented on your post.', $name),
            NotificationType::CommentReplied => sprintf('%s replied to your comment.', $name),
            NotificationType::PostShared => sprintf('%s shared your post.', $name),
            NotificationType::NewMessage => sprintf('%s sent you a message.', $name),
            default => sprintf('%s has new activity.', $name),
        };
    }

    private function isBlocked(User $recipient, User $actor): bool
    {
        return DB::table('blocked_users')
            ->where('user_id', $recipient->id)
            ->where('blocked_id', $actor->id)
            ->exists();
    }
}
