<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Message
 */
final class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ?\App\Models\User $viewer */
        $viewer = $request->user();

        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender' => new UserResource($this->whenLoaded('sender')),
            'type' => $this->type,
            'body' => $this->body,

            // Emoji-only messages render large and bubble-free in the UI.
            'is_emoji_only' => $this->isEmojiOnly(),

            'media' => $this->whenLoaded('media', fn () => $this->media->map(fn ($m): array => [
                'id' => $m->id,
                'type' => $m->type,
                'url' => $m->url,
                'thumbnail_url' => $m->thumbnail_url,
                'original_name' => $m->original_name,
                'mime_type' => $m->mime_type,
                'size' => $m->size,
                'width' => $m->width,
                'height' => $m->height,
                'duration' => $m->duration,
            ])->all(), []),

            'reply_to' => $this->when(
                $this->relationLoaded('replyTo') && $this->replyTo !== null,
                fn (): array => [
                    'id' => $this->replyTo->id,
                    'body' => $this->replyTo->body,
                    'sender_name' => $this->replyTo->sender?->name(),
                ]
            ),

            'reactions' => $this->whenLoaded('reactionRecords', fn () => $this->reactionRecords
                ->groupBy('reaction')
                ->map(fn ($group): array => [
                    'reaction' => $group->first()->reaction,
                    'count' => $group->count(),
                    'users' => $group->map(fn ($r): string => (string) $r->user_id)->all(),
                ])->values()->all(), []),

            'delivery_status' => $this->delivery_status,
            'read_at' => $this->read_at?->toIso8601String(),
            'edited_at' => $this->edited_at?->toIso8601String(),
            'deleted_for_everyone_at' => $this->deleted_for_everyone_at?->toIso8601String(),

            'is_mine' => $viewer !== null && $viewer->id === $this->sender_id,
            'is_deleted_for_me' => $viewer !== null && $this->isDeletedFor($viewer),

            'read_by' => $this->when(
                $this->relationLoaded('reads'),
                fn () => $this->reads->map(fn ($r): array => [
                    'user_id' => $r->user_id,
                    'read_at' => $r->read_at?->toIso8601String(),
                ])->all(),
                []
            ),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
