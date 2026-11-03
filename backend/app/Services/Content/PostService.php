<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Enums\Audience;
use App\Enums\PostType;
use App\Jobs\GenerateLinkPreview;
use App\Models\Post;
use App\Models\PostEditHistory;
use App\Models\PostLink;
use App\Models\User;
use App\Services\Feed\FeedCacheService;
use App\Services\Media\MediaUploadService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates, edits, shares and deletes posts.
 *
 * Every write goes through this service rather than a controller so the side
 * effects stay in one place: parsing mentions/hashtags, extracting links,
 * attaching media, writing edit history, updating counters and invalidating
 * the author's friends' feed caches.
 */
final class PostService
{
    /** Regex fragments kept in one place: the frontend linkify util mirrors these. */
    private const string URL_PATTERN = '#\bhttps?://[^\s<>()\[\]{}"\']+#i';
    private const string MENTION_PATTERN = '/@\[([^\]]+)\]\(([0-9a-f\-]{36})\)/';
    private const string HASHTAG_PATTERN = '/#([\p{L}\p{N}_]{1,50})/u';

    public function __construct(
        private readonly MediaUploadService $media,
        private readonly FeedCacheService $feedCache,
    ) {
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, mixed>  $uploads  Uploaded files (\Illuminate\Http\UploadedFile[])
     */
    public function create(User $author, array $attributes, array $uploads = []): Post
    {
        return DB::transaction(function () use ($author, $attributes, $uploads): Post {
            $body = (string) ($attributes['body'] ?? '');
            $type = $this->resolveType($attributes, $uploads);

            /** @var Post $post */
            $post = Post::create([
                'user_id' => $author->id,
                'group_id' => $attributes['group_id'] ?? null,
                'page_id' => $attributes['page_id'] ?? null,
                'event_id' => $attributes['event_id'] ?? null,
                'type' => $type->value,
                'body' => $body !== '' ? $body : null,
                'background' => $attributes['background'] ?? null,
                'audience' => $attributes['audience'] ?? Audience::Friends->value,
                'feeling' => $attributes['feeling'] ?? null,
                'activity' => $attributes['activity'] ?? null,
                'location_name' => $attributes['location_name'] ?? null,
                'latitude' => $attributes['latitude'] ?? null,
                'longitude' => $attributes['longitude'] ?? null,
                'life_event_type' => $attributes['life_event_type'] ?? null,
                'shared_post_id' => $attributes['shared_post_id'] ?? null,
                'share_comment' => $attributes['share_comment'] ?? null,
                'is_pending_approval' => (bool) ($attributes['is_pending_approval'] ?? false),
                'published_at' => $attributes['published_at'] ?? now(),
            ]);

            $this->attachMedia($post, $uploads);
            $this->syncAudienceList($post, $attributes);
            $this->syncFeelings($post, $attributes);
            $this->syncMentions($post, $body);
            $this->syncHashtags($post, $body);
            $this->extractLink($post, $body);
            $this->syncPoll($post, $attributes);

            if ($post->group_id !== null) {
                $post->group?->groupPosts()->create([
                    'post_id' => $post->id,
                    'status' => $post->is_pending_approval ? 'pending' : 'approved',
                ]);
            }

            if ($post->page_id !== null) {
                $post->page?->pagePosts()->create(['post_id' => $post->id]);
            }

            $author->profile?->increment('posts_count');

            return $post->fresh(['media', 'link', 'poll.options', 'feelings', 'author']);
        });
    }

    /**
     * Edit a post, keeping the previous body/audience in `post_edit_history`
     * (Facebook shows the full edit history to the author, and a court-grade
     * trail to moderators).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Post $post, array $attributes, ?User $editor = null): Post
    {
        return DB::transaction(function () use ($post, $attributes, $editor): Post {
            $editor ??= $post->author;

            if (array_key_exists('body', $attributes) && $attributes['body'] !== $post->body) {
                PostEditHistory::create([
                    'post_id' => $post->id,
                    'editor_id' => $editor->id,
                    'previous_body' => $post->body,
                    'previous_audience' => $post->audience,
                ]);
            }

            $updates = array_filter([
                'body' => $attributes['body'] ?? null,
                'audience' => $attributes['audience'] ?? null,
                'background' => $attributes['background'] ?? null,
                'feeling' => $attributes['feeling'] ?? null,
                'activity' => $attributes['activity'] ?? null,
                'location_name' => $attributes['location_name'] ?? null,
                'comments_enabled' => $attributes['comments_enabled'] ?? null,
            ], static fn (mixed $v): bool => $v !== null);

            $updates['is_edited'] = true;

            $post->forceFill($updates)->save();

            if (isset($attributes['body'])) {
                $this->syncMentions($post, (string) $attributes['body']);
                $this->syncHashtags($post, (string) $attributes['body']);
                $this->extractLink($post, (string) $attributes['body']);
            }

            return $post->fresh(['media', 'link.preview', 'poll.options', 'feelings', 'author']);
        });
    }

    public function delete(Post $post): void
    {
        DB::transaction(function () use ($post): void {
            $post->author?->profile?->decrement('posts_count');
            $post->delete();
        });
    }

    /**
     * Share an existing post. The share is a real post of its own (so it can
     * carry a comment, an audience and appear on a timeline) that points at
     * the original instead of duplicating its media.
     */
    public function share(User $user, Post $original, array $attributes = []): Post
    {
        $post = $this->create($user, [
            'type' => PostType::Shared->value,
            'body' => $attributes['body'] ?? null,
            'audience' => $attributes['audience'] ?? Audience::Friends->value,
            'shared_post_id' => $original->id,
            'share_comment' => $attributes['body'] ?? null,
            'group_id' => $attributes['group_id'] ?? null,
            'page_id' => $attributes['page_id'] ?? null,
        ]);

        $original->withoutEvents(fn () => $original->increment('shares_count'));

        \App\Models\SharedPost::create([
            'post_id' => $original->id,
            'user_id' => $user->id,
            'audience' => $attributes['audience'] ?? Audience::Friends->value,
            'comment' => $attributes['body'] ?? null,
        ]);

        return $post;
    }

    /**
     * Change a post's audience without touching its body.
     */
    public function changeAudience(Post $post, Audience $audience, array $userIds = []): Post
    {
        return DB::transaction(function () use ($post, $audience, $userIds): Post {
            PostEditHistory::create([
                'post_id' => $post->id,
                'editor_id' => $post->user_id,
                'previous_body' => null,
                'previous_audience' => $post->audience,
                'reason' => 'audience_changed',
            ]);

            $post->forceFill(['audience' => $audience->value])->save();
            $this->syncAudienceList($post, ['audience' => $audience->value, 'audience_user_ids' => $userIds]);

            return $post->fresh();
        });
    }

    // ------------------------------------------------------------- internals

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, mixed>  $uploads
     */
    private function resolveType(array $attributes, array $uploads): PostType
    {
        if (isset($attributes['type'])) {
            return PostType::tryFrom((string) $attributes['type']) ?? PostType::Text;
        }

        return match (true) {
            ($attributes['shared_post_id'] ?? null) !== null => PostType::Shared,
            ($attributes['poll'] ?? null) !== null => PostType::Poll,
            ($attributes['life_event_type'] ?? null) !== null => PostType::LifeEvent,
            ($attributes['location_name'] ?? null) !== null => PostType::CheckIn,
            ($attributes['feeling'] ?? null) !== null || ($attributes['activity'] ?? null) !== null => PostType::Feeling,
            ($attributes['gif_url'] ?? null) !== null => PostType::Gif,
            count($uploads) > 1 => PostType::Album,
            count($uploads) === 1 => $this->media->looksLikeVideo($uploads[0]) ? PostType::Video : PostType::Photo,
            $this->firstUrl((string) ($attributes['body'] ?? '')) !== null => PostType::Link,
            default => PostType::Text,
        };
    }

    /**
     * @param  array<int, mixed>  $uploads
     */
    private function attachMedia(Post $post, array $uploads): void
    {
        foreach (array_values($uploads) as $index => $upload) {
            $stored = $this->media->storePostMedia($upload, $post);

            $post->media()->create(array_merge($stored, ['position' => $index]));
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function syncAudienceList(Post $post, array $attributes): void
    {
        $audience = Audience::tryFrom((string) ($attributes['audience'] ?? Audience::Friends->value));

        if ($audience === null || ! $audience->requiresUserList()) {
            $post->audienceEntries()->delete();

            return;
        }

        /** @var list<string> $ids */
        $ids = array_values(array_unique((array) ($attributes['audience_user_ids'] ?? [])));

        $post->audienceEntries()->delete();

        foreach ($ids as $id) {
            $post->audienceEntries()->create([
                'user_id' => $id,
                'is_allowed' => $audience === Audience::Specific,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function syncFeelings(Post $post, array $attributes): void
    {
        $post->feelings()->delete();

        if (($attributes['feeling'] ?? null) !== null) {
            $post->feelings()->create([
                'kind' => 'feeling',
                'value' => (string) $attributes['feeling'],
                'emoji' => $attributes['feeling_emoji'] ?? null,
            ]);
        }

        if (($attributes['activity'] ?? null) !== null) {
            $post->feelings()->create([
                'kind' => 'activity',
                'value' => (string) $attributes['activity'],
                'emoji' => $attributes['activity_emoji'] ?? null,
            ]);
        }
    }

    /**
     * Mentions arrive from the composer as `@[Display Name](uuid)` so a renamed
     * user is resolved at write time and never by string matching at read time.
     */
    private function syncMentions(Post $post, string $body): void
    {
        $post->mentions()->delete();

        if (preg_match_all(self::MENTION_PATTERN, $body, $matches, PREG_SET_ORDER) === false) {
            return;
        }

        foreach ($matches as $match) {
            if (! Str::isUuid($match[2])) {
                continue;
            }

            $post->mentions()->firstOrCreate(
                ['mentioned_user_id' => $match[2]],
                ['offset' => 0, 'length' => mb_strlen($match[1])]
            );
        }
    }

    private function syncHashtags(Post $post, string $body): void
    {
        $post->hashtagRows()->delete();

        if (preg_match_all(self::HASHTAG_PATTERN, $body, $matches) === false) {
            return;
        }

        foreach (array_unique($matches[1]) as $tag) {
            $post->hashtagRows()->create(['tag' => Str::lower($tag)]);
        }
    }

    /**
     * Persist the first URL in the body and dispatch the metadata fetch to the
     * queue — never inline, since an unresponsive host must not slow posting.
     */
    private function extractLink(Post $post, string $body): void
    {
        $url = $this->firstUrl($body);

        $post->link()?->delete();

        if ($url === null) {
            return;
        }

        $host = parse_url($url, PHP_URL_HOST);

        /** @var PostLink $link */
        $link = $post->link()->create([
            'url' => $url,
            'domain' => is_string($host) ? $host : '',
            'is_preview_enabled' => true,
        ]);

        GenerateLinkPreview::dispatch($link);
    }

    private function firstUrl(string $body): ?string
    {
        if (preg_match(self::URL_PATTERN, $body, $matches) !== 1) {
            return null;
        }

        return $matches[0];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function syncPoll(Post $post, array $attributes): void
    {
        $poll = $attributes['poll'] ?? null;

        if (! is_array($poll) || ! isset($poll['question'], $poll['options'])) {
            return;
        }

        /** @var \App\Models\Poll $model */
        $model = $post->poll()->create([
            'question' => (string) $poll['question'],
            'allows_multiple' => (bool) ($poll['allows_multiple'] ?? false),
            'closes_at' => $poll['closes_at'] ?? null,
        ]);

        foreach (array_values((array) $poll['options']) as $index => $label) {
            $model->options()->create(['label' => (string) $label, 'position' => $index]);
        }
    }

    /**
     * Timeline for a profile page, newest first, with the same eager loads the
     * feed uses so the card component behaves identically everywhere.
     *
     * @return Collection<int, Post>
     */
    public function timelineFor(User $user, ?User $viewer = null, int $limit = 15): Collection
    {
        $query = Post::query()
            ->published()
            ->timeline($user)
            ->with(['media', 'link.preview', 'poll.options', 'feelings', 'author', 'sharedPost.author'])
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->limit($limit);

        if ($viewer === null || $viewer->id !== $user->id) {
            $query->visibleTo($viewer);
        }

        return $query->get();
    }
}
