<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Renders the bell row.
 *
 * The `text` field is the aggregation-aware sentence the UI shows verbatim:
 * "Ava liked your photo" for one actor, "Ava and 4 others liked your photo"
 * once several people act on the same subject inside the window.
 *
 * @mixin Notification
 */
final class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $count = (int) $this->aggregated_count;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'actor' => new UserResource($this->whenLoaded('actor')),
            'aggregated_count' => $count,
            'text' => $this->text($count),
            'icon' => $this->icon(),

            'notifiable_type' => $this->notifiable_type,
            'notifiable_id' => $this->notifiable_id,
            'data' => $this->data,

            'is_read' => $this->isRead(),
            'read_at' => $this->read_at?->toIso8601String(),

            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_human' => $this->created_at?->diffForHumans(),
        ];
    }

    private function text(int $count): string
    {
        $name = $this->actor?->name() ?? 'Someone';
        $others = $count > 1 ? sprintf(' and %d %s', $count - 1, $count === 2 ? 'other' : 'others') : '';
        $subject = $count > 1 ? 's' : '';

        return match ($this->typeEnum()) {
            \App\Enums\NotificationType::FriendRequestReceived => sprintf('%s sent you a friend request.', $name),
            \App\Enums\NotificationType::FriendRequestAccepted => sprintf('%s accepted your friend request.', $name),
            \App\Enums\NotificationType::PostReacted => sprintf('%s%s reacted to your post.', $name, $others),
            \App\Enums\NotificationType::CommentReacted => sprintf('%s%s reacted to your comment.', $name, $others),
            \App\Enums\NotificationType::PostCommented => sprintf('%s%s commented on your post.', $name, $others),
            \App\Enums\NotificationType::CommentReplied => sprintf('%s%s replied to your comment.', $name, $others),
            \App\Enums\NotificationType::PostShared => sprintf('%s%s shared your post.', $name, $others),
            \App\Enums\NotificationType::NewMessage => sprintf('%s sent you %s.', $name, $count > 1 ? $count.' messages' : 'a message'),
            \App\Enums\NotificationType::TaggedInPost => sprintf('%s tagged you in a post.', $name),
            \App\Enums\NotificationType::BirthdayReminder => sprintf('%s has a birthday today.', $name),
            \App\Enums\NotificationType::MemoryAvailable => 'You have memories to look back on today.',
            default => $this->body ?? sprintf('%s%s has new activity.', $name, $others),
        };
    }

    private function icon(): string
    {
        return match ($this->typeEnum()) {
            \App\Enums\NotificationType::FriendRequestReceived => 'user-plus',
            \App\Enums\NotificationType::FriendRequestAccepted => 'friends',
            \App\Enums\NotificationType::PostReacted,
            \App\Enums\NotificationType::CommentReacted => 'like',
            \App\Enums\NotificationType::PostCommented,
            \App\Enums\NotificationType::CommentReplied => 'comment',
            \App\Enums\NotificationType::PostShared => 'share',
            \App\Enums\NotificationType::NewMessage => 'messenger',
            \App\Enums\NotificationType::BirthdayReminder => 'cake',
            \App\Enums\NotificationType::MemoryAvailable => 'clock',
            default => 'bell',
        };
    }
}
