<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The exact payload the React `PostCard` renders.
 *
 * Shaped deliberately: everything the card needs is present in one object so
 * the client never has to waterfall extra requests just to render a feed row
 * (no "fetch author", no "fetch reaction summary").
 *
 * @mixin Post
 */
final class PostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ?User $viewer */
        $viewer = $request->user();

        $reaction = $viewer !== null
            ? $this->resource->reactionBy($viewer)
            : null;

        $saved = $viewer !== null
            ? $this->resource->savedBy()->where('user_id', $viewer->id)->exists()
            : false;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'body' => $this->body,
            'background' => $this->background,

            'author' => new UserResource($this->whenLoaded('author')),

            // The rendered shape of the photo grid: the frontend keys off the
            // media count to choose between the 1 / 2 / 3 / 4 / 5+ layouts.
            'media' => PostMediaResource::collection($this->whenLoaded('media')),
            'media_count' => $this->whenCounted('media', (int) ($this->media_count ?? 0)),

            'link' => $this->when(
                $this->relationLoaded('link') && $this->link !== null && $this->link->preview !== null,
                fn (): array => [
                    'url' => $this->link->url,
                    'domain' => $this->link->domain,
                    'title' => $this->link->preview?->title,
                    'description' => $this->link->preview?->description,
                    'image_url' => $this->link->preview?->image_url,
                    'site_name' => $this->link->preview?->site_name,
                ]
            ),

            'poll' => $this->when(
                $this->relationLoaded('poll') && $this->poll !== null,
                fn (): array => [
                    'id' => $this->poll->id,
                    'question' => $this->poll->question,
                    'allows_multiple' => $this->poll->allows_multiple,
                    'is_open' => $this->poll->isOpen(),
                    'total_votes' => $this->poll->totalVotes(),
                    'voted_option_ids' => $viewer !== null
                        ? $this->poll->options()
                            ->whereHas('votes', fn ($q) => $q->where('user_id', $viewer->id))
                            ->pluck('id')
                            ->all()
                        : [],
                    'options' => $this->poll->options->map(fn ($option): array => [
                        'id' => $option->id,
                        'label' => $option->label,
                        'votes_count' => $option->votes_count,
                        'percentage' => $option->percentage(),
                    ])->all(),
                ]
            ),

            'feelings' => $this->whenLoaded('feelings', fn () => $this->feelings->map(fn ($f): array => [
                'kind' => $f->kind,
                'value' => $f->value,
                'emoji' => $f->emoji,
            ])->all(), []),

            'shared_post' => new self($this->whenLoaded('sharedPost')),

            'audience' => $this->audience,
            'feeling' => $this->feeling,
            'activity' => $this->activity,
            'location_name' => $this->location_name,
            'life_event_type' => $this->life_event_type,

            // Counters.
            'reactions_count' => (int) $this->reactions_count,
            'comments_count' => (int) $this->comments_count,
            'shares_count' => (int) $this->shares_count,
            'views_count' => (int) $this->views_count,

            // Reaction bar: the top three types plus the viewer's own reaction.
            'reaction_summary' => $this->resource->reactionSummary(),
            'top_reactions' => $this->resource->topReactions(3),
            'viewer_reaction' => $reaction?->type,

            'is_saved' => $saved,
            'is_pinned' => (bool) $this->is_pinned,
            'is_edited' => (bool) $this->is_edited,
            'comments_enabled' => (bool) $this->comments_enabled,

            // Permissions drive the "…" menu: only the owner sees edit/delete.
            'can' => [
                'edit' => $viewer !== null && $viewer->id === $this->user_id,
                'delete' => $viewer !== null && ($viewer->id === $this->user_id || $viewer->can('manageContent')),
                'comment' => (bool) $this->comments_enabled,
                'share' => $this->audience !== 'only_me',
            ],

            'group' => $this->when(
                $this->relationLoaded('group') && $this->group !== null,
                fn (): array => ['id' => $this->group->id, 'name' => $this->group->name]
            ),

            'page' => $this->when(
                $this->relationLoaded('page') && $this->page !== null,
                fn (): array => ['id' => $this->page->id, 'name' => $this->page->name]
            ),

            'created_at' => $this->created_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'edited_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
