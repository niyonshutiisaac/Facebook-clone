<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Audience;
use App\Enums\PostType;
use App\Traits\HasComments;
use App\Traits\HasPrivacy;
use App\Traits\HasReactions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Post extends Model
{
    /** @use HasFactory<\Database\Factories\PostFactory> */
    use HasComments;
    use HasFactory;
    use HasPrivacy;
    use HasReactions;
    use Searchable;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id', 'group_id', 'page_id', 'event_id',
        'type', 'body', 'background', 'audience',
        'feeling', 'activity', 'location_name', 'latitude', 'longitude', 'life_event_type',
        'shared_post_id', 'share_comment',
        'comments_count', 'shares_count', 'reactions_count', 'views_count',
        'engagement_velocity', 'quality_score', 'ranking_score',
        'is_pinned', 'is_hidden', 'is_edited', 'comments_enabled', 'is_pending_approval',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'comments_count' => 'integer',
            'shares_count' => 'integer',
            'reactions_count' => 'integer',
            'views_count' => 'integer',
            'engagement_velocity' => 'decimal:4',
            'quality_score' => 'decimal:4',
            'ranking_score' => 'decimal:6',
            'is_pinned' => 'boolean',
            'is_hidden' => 'boolean',
            'is_edited' => 'boolean',
            'comments_enabled' => 'boolean',
            'is_pending_approval' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    // ------------------------------------------------------------- accessors

    public function typeEnum(): PostType
    {
        return PostType::tryFrom((string) $this->type) ?? PostType::Text;
    }

    /**
     * Media-backed types render a grid/video player in the feed card.
     */
    public function hasMedia(): bool
    {
        return in_array($this->typeEnum(), [PostType::Photo, PostType::Video, PostType::Album, PostType::Gif], true);
    }

    public function excerpt(int $length = 180): string
    {
        $body = strip_tags((string) $this->body);

        return mb_strlen($body) <= $length ? $body : mb_substr($body, 0, $length).'…';
    }

    /**
     * Hashtags and mentions are extracted by App\Services\Content\PostService
     * and cached here so the feed never re-parses post bodies on every render.
     *
     * @return list<string>
     */
    public function hashtags(): array
    {
        return $this->hashtagRows->pluck('tag')->all();
    }

    // ---------------------------------------------------------- relationships

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Rows in the `group_posts` join table for this post — one per group it was
     * shared into, each with its own moderation status.
     *
     * @return HasMany<GroupPost, $this>
     */
    public function groupPosts(): HasMany
    {
        return $this->hasMany(GroupPost::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(PostMedia::class)->orderBy('position');
    }

    public function link(): HasOne
    {
        return $this->hasOne(PostLink::class);
    }

    public function mentions(): HasMany
    {
        return $this->hasMany(PostMention::class);
    }

    public function mentionedUsers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_mentions', 'post_id', 'mentioned_user_id');
    }

    public function hashtagRows(): HasMany
    {
        return $this->hasMany(PostHashtag::class);
    }

    public function feelings(): HasMany
    {
        return $this->hasMany(PostFeeling::class);
    }

    public function audienceEntries(): HasMany
    {
        return $this->hasMany(PostAudience::class);
    }

    public function editHistory(): HasMany
    {
        return $this->hasMany(PostEditHistory::class)->latest();
    }

    public function poll(): HasOne
    {
        return $this->hasOne(Poll::class);
    }

    public function sharedPost(): BelongsTo
    {
        return $this->belongsTo(self::class, 'shared_post_id');
    }

    public function shares(): HasMany
    {
        return $this->hasMany(SharedPost::class);
    }

    public function savedBy(): HasMany
    {
        return $this->hasMany(SavedPost::class);
    }

    /**
     * @return MorphMany<Reaction>
     */
    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    /**
     * @return Collection<int, PostMedia>
     */
    public function orderedMedia(): Collection
    {
        return $this->media()->orderBy('position')->get();
    }

    // ---------------------------------------------------------------- scopes

    /**
     * Only posts the viewer is allowed to see, at the database level.
     *
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    public function scopeVisibleTo(Builder $query, ?User $viewer): Builder
    {
        if ($viewer === null) {
            return $query->where('audience', Audience::Public->value);
        }

        $friendIds = $viewer->friendIds();

        return $query->where(function (Builder $q) use ($viewer, $friendIds): void {
            $q->where('audience', Audience::Public->value)
                ->orWhere(fn (Builder $b) => $b->where('audience', Audience::Friends->value)
                    ->whereIn('user_id', $friendIds))
                ->orWhere(fn (Builder $b) => $b->where('user_id', $viewer->id));
        })->whereNotIn('user_id', $viewer->blockedIds());
    }

    /**
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where('is_hidden', false);
    }

    /**
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    public function scopeTimeline(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id)
            ->whereNull('group_id')
            ->whereNull('page_id');
    }

    /**
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    public function scopeOfType(Builder $query, PostType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    // -------------------------------------------------------------- scout ----

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'author_name' => $this->author?->name(),
            'body' => $this->body,
            'type' => $this->type,
            'audience' => $this->audience,
            'created_at' => $this->created_at?->timestamp,
        ];
    }

    /**
     * Private posts must never reach the search index.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->audience === Audience::Public->value && $this->published_at !== null;
    }
}
