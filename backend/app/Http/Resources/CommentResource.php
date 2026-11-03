<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Comment
 */
final class CommentResource extends JsonResource
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
            'post_id' => $this->post_id,
            'parent_id' => $this->parent_id,
            'depth' => (int) $this->depth,
            'body' => $this->body,
            'author' => new UserResource($this->whenLoaded('author')),
            'media' => $this->whenLoaded('media', fn () => $this->media->map(fn ($m): array => [
                'id' => $m->id,
                'type' => $m->type,
                'url' => $m->url,
            ])->all(), []),

            'reactions_count' => (int) $this->reactions_count,
            'reaction_summary' => $this->resource->reactionSummary(),
            'viewer_reaction' => $viewer !== null ? $this->resource->reactionBy($viewer)?->type : null,

            'replies_count' => (int) ($this->replies_count ?? 0),
            'replies' => CommentResource::collection($this->whenLoaded('replies')),

            'is_edited' => (bool) $this->is_edited,
            'is_hidden' => (bool) $this->is_hidden,

            'can' => [
                'edit' => $viewer !== null && $viewer->id === $this->user_id,
                'delete' => $viewer !== null
                    && ($viewer->id === $this->user_id
                        || $viewer->id === $this->post?->user_id),
                'reply' => true,
            ],

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
