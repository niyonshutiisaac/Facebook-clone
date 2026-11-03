<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Story;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Story
 */
final class StoryResource extends JsonResource
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
            'type' => $this->type,
            'body' => $this->body,
            'background' => $this->background,
            'author' => new UserResource($this->whenLoaded('author')),

            'media' => $this->whenLoaded('media', fn () => $this->media->map(fn ($m): array => [
                'id' => $m->id,
                'type' => $m->type,
                'url' => $m->url,
                'thumbnail_url' => $m->thumbnail_url,
                'width' => $m->width,
                'height' => $m->height,
                'duration' => $m->duration,
            ])->all(), []),

            'stickers' => $this->whenLoaded('stickers', fn () => $this->stickers->map(fn ($s): array => [
                'id' => $s->id,
                'type' => $s->type,
                'payload' => $s->payload,
                'x_position' => $s->x_position,
                'y_position' => $s->y_position,
                'scale' => $s->scale,
                'rotation' => $s->rotation,
            ])->all(), []),

            'duration' => (int) $this->duration,
            'views_count' => (int) $this->views_count,
            'replies_count' => (int) $this->replies_count,

            'is_seen' => $viewer !== null
                ? $this->viewers()->where('viewer_id', $viewer->id)->exists()
                : false,
            'is_own' => $viewer !== null && $viewer->id === $this->user_id,
            'is_archived' => (bool) $this->is_archived,
            'is_highlighted' => (bool) $this->is_highlighted,

            'created_at' => $this->created_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
