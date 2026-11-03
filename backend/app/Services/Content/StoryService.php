<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Enums\ReactionType;
use App\Models\Story;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

/**
 * Stories: create, tray assembly, view tracking and replies.
 *
 * Stories expire 24 hours after publication. The tray is assembled per author
 * (not per story) because that is how Facebook renders it: one circle per
 * person, unseen circles first.
 */
final class StoryService
{
    public function __construct(
        private readonly \App\Services\Media\MediaUploadService $media,
        private readonly NotificationService $notifications,
    ) {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $author, array $attributes, ?UploadedFile $file = null): Story
    {
        return DB::transaction(function () use ($author, $attributes, $file): Story {
            /** @var Story $story */
            $story = Story::create([
                'user_id' => $author->id,
                'type' => $attributes['type'] ?? 'photo',
                'body' => $attributes['body'] ?? null,
                'background' => $attributes['background'] ?? null,
                'audience' => $attributes['audience'] ?? 'friends',
                'duration' => (int) ($attributes['duration'] ?? 5),
                'expires_at' => now()->addHours(24),
            ]);

            if ($file !== null) {
                $path = \Illuminate\Support\Facades\Storage::disk(config('filesystems.default'))->putFile(
                    sprintf('stories/%s/%s', $author->id, now()->format('Y/m')),
                    $file,
                    ['visibility' => 'public']
                );

                $story->media()->create([
                    'type' => str_starts_with((string) $file->getMimeType(), 'video') ? 'video' : 'image',
                    'path' => $path,
                    'disk' => config('filesystems.default'),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }

            foreach ((array) ($attributes['stickers'] ?? []) as $sticker) {
                $story->stickers()->create([
                    'type' => $sticker['type'],
                    'payload' => $sticker['payload'] ?? null,
                    'x_position' => $sticker['x_position'] ?? 50,
                    'y_position' => $sticker['y_position'] ?? 50,
                    'scale' => $sticker['scale'] ?? 1,
                    'rotation' => $sticker['rotation'] ?? 0,
                ]);
            }

            return $story->fresh(['media', 'stickers']);
        });
    }

    /**
     * One tray per author: the viewer's own story first, then friends with
     * unseen stories, then friends whose stories have been seen.
     *
     * @return SupportCollection<int, array{user: User, stories: Collection<int, Story>, has_unseen: bool, latest_at: string}>
     */
    public function trayFor(User $viewer): SupportCollection
    {
        $friendIds = $viewer->friendIds();
        $friendIds[] = $viewer->id;

        /** @var Collection<int, Story> $stories */
        $stories = Story::query()
            ->live()
            ->with(['author', 'media', 'stickers', 'viewers'])
            ->whereIn('user_id', $friendIds)
            ->whereIn('audience', ['public', 'friends', 'close_friends'])
            ->orderByDesc('created_at')
            ->get();

        $seen = DB::table('story_viewers')
            ->where('viewer_id', $viewer->id)
            ->pluck('story_id')
            ->all();

        $grouped = $stories
            ->groupBy('user_id')
            ->map(function (Collection $group) use ($viewer, $seen): array {
                $unseen = $group->reject(fn (Story $s): bool => in_array($s->id, $seen, true));

                return [
                    'user' => $group->first()->author,
                    'stories' => $group->values(),
                    'has_unseen' => $unseen->isNotEmpty(),
                    'latest_at' => (string) $group->first()->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->sortBy([
                fn (array $a, array $b): int => (int) $b['has_unseen'] <=> (int) $a['has_unseen'],
                fn (array $a, array $b): int => strcmp($b['latest_at'], $a['latest_at']),
            ]);

        return $grouped->values();
    }

    public function markViewed(Story $story, User $viewer): void
    {
        if ($story->user_id === $viewer->id) {
            return;
        }

        $created = $story->viewers()->firstOrCreate(
            ['viewer_id' => $viewer->id],
            ['viewed_at' => now()]
        );

        if ($created->wasRecentlyCreated) {
            $story->increment('views_count');
        }
    }

    public function react(Story $story, User $viewer, string $reaction): void
    {
        $story->reactions()->updateOrCreate(
            ['user_id' => $viewer->id],
            ['type' => $reaction]
        );

        $story->increment('reactions_count');

        $this->notifications->notify(
            $story->author,
            \App\Enums\NotificationType::StoryReacted,
            $viewer,
            $story,
            ['reaction' => $reaction, 'emoji' => ReactionType::tryFrom($reaction)?->emoji() ?? '👍']
        );
    }

    public function reply(Story $story, User $viewer, string $body): void
    {
        $story->replies()->create([
            'user_id' => $viewer->id,
            'body' => $body,
        ]);

        $story->increment('replies_count');

        $this->notifications->notify(
            $story->author,
            \App\Enums\NotificationType::StoryReplied,
            $viewer,
            $story,
            ['preview' => \Illuminate\Support\Str::limit($body, 120)]
        );
    }

    /**
     * Called by the scheduled command: anything older than 24 hours is archived
     * (kept, hidden) rather than hard-deleted, so "story archive" works.
     */
    public function archiveExpired(): int
    {
        $expired = Story::query()
            ->where('expires_at', '<=', now())
            ->where('is_archived', false)
            ->get();

        foreach ($expired as $story) {
            $story->forceFill(['is_archived' => true])->saveQuietly();
        }

        return $expired->count();
    }
}
