<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Conversation
 */
final class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Models\User $viewer */
        $viewer = $request->user();

        $pivot = $this->participantRecords->firstWhere('user_id', $viewer->id);

        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->displayNameFor($viewer),
            'avatar_url' => $this->avatar_path !== null
                ? $this->avatar_url
                : ($this->typeEnum() === \App\Enums\ConversationType::Private
                    ? $this->otherParticipant($viewer)?->avatar_url
                    : null),
            'theme_color' => $this->theme_color,
            'emoji_icon' => $this->emoji_icon,

            'participants' => UserResource::collection($this->whenLoaded('participants')),

            'last_message' => new MessageResource($this->whenLoaded('lastMessage')),
            'last_message_at' => $this->last_message_at?->toIso8601String(),

            'unread_count' => $this->unreadCountFor($viewer),
            'is_muted' => (bool) $pivot?->is_muted,
            'is_pinned' => (bool) $pivot?->is_pinned,
            'is_archived' => (bool) $pivot?->is_archived,
            'last_read_at' => $pivot?->last_read_at?->toIso8601String(),

            // Who is typing right now, for this conversation's presence channel.
            'typing_user_ids' => $this->participantRecords
                ->filter(fn ($p): bool => (bool) $p->is_typing
                    && $p->typing_at !== null
                    && $p->typing_at->gt(now()->subSeconds(10))
                    && $p->user_id !== $viewer->id)
                ->pluck('user_id')
                ->values()
                ->all(),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
