<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Models\Reel;
use App\Models\ReelView;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Reels: creation, the (content-first) recommendation feed and view tracking.
 *
 * Unlike the news feed, the reels feed is not limited to your graph: it ranks
 * by predicted watch-through, with periodic boosts for new creators and
 * trending audio so the surface does not collapse into a popularity contest.
 */
final class ReelService
{
    /** How many reels ahead of the viewer the client preloads. */
    public const int PRELOAD_COUNT = 2;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $creator, array $attributes, UploadedFile $video): Reel
    {
        $path = Storage::disk(config('filesystems.default'))->putFile(
            sprintf('reels/%s/%s', $creator->id, now()->format('Y/m')),
            $video,
            ['visibility' => 'public']
        );

        /** @var Reel $reel */
        $reel = Reel::create([
            'user_id' => $creator->id,
            'audio_id' => $attributes['audio_id'] ?? null,
            'path' => $path,
            'disk' => config('filesystems.default'),
            'original_name' => $video->getClientOriginalName(),
            'mime_type' => $video->getMimeType(),
            'size' => $video->getSize(),
            'caption' => $attributes['caption'] ?? null,
            'audience' => $attributes['audience'] ?? 'public',
            'allows_remix' => (bool) ($attributes['allows_remix'] ?? true),
            'status' => 'processing',
        ]);

        // Transcoding + thumbnail extraction happen out of band.
        \App\Jobs\ProcessVideoUpload::dispatch($reel);

        return $reel->fresh(['creator', 'audio']);
    }

    /**
     * @return array{items: \Illuminate\Support\Collection<int, Reel>, cursor: ?string, has_more: bool}
     */
    public function feed(User $viewer, ?string $cursor = null, int $limit = 10): array
    {
        $query = Reel::query()
            ->with(['creator', 'audio'])
            ->where('status', 'ready')
            ->whereIn('audience', ['public', 'friends']);

        if ($cursor !== null) {
            $decoded = \App\Support\Cursor::decode($cursor);

            if (isset($decoded['i'])) {
                $anchor = Reel::query()->find($decoded['i']);

                if ($anchor !== null) {
                    $query->where(function ($q) use ($anchor): void {
                        $q->where('ranking_score', '<', $anchor->ranking_score)
                            ->orWhere(fn ($b) => $b->where('ranking_score', '=', $anchor->ranking_score)
                                ->where('id', '<', $anchor->id));
                    });
                }
            }
        }

        $reels = $query->orderByDesc('ranking_score')
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $reels->count() > $limit;
        $items = $reels->slice(0, $limit)->values();
        $last = $items->last();

        return [
            'items' => $items,
            'cursor' => $hasMore && $last !== null
                ? \App\Support\Cursor::encode(['i' => $last->id, 's' => (float) $last->ranking_score])
                : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Watch-through is the dominant ranking input for short video: a reel
     * watched to the end twice is a far stronger signal than a like.
     */
    public function recordView(Reel $reel, User $viewer, int $watchedSeconds, int $replays = 0): void
    {
        $duration = max(1, (int) ($reel->duration ?? 1));
        $completion = min(1.0, $watchedSeconds / $duration);

        ReelView::query()->updateOrCreate(
            ['reel_id' => $reel->id, 'user_id' => $viewer->id],
            [
                'watched_seconds' => $watchedSeconds,
                'completion_rate' => $completion,
                'replays' => $replays,
            ]
        );

        $reel->increment('views_count');

        $reel->forceFill([
            'completion_rate' => (float) $reel->views()->avg('completion_rate'),
            'ranking_score' => $this->rankingScore($reel),
        ])->saveQuietly();
    }

    public function comment(Reel $reel, User $author, string $body): \App\Models\ReelComment
    {
        $comment = $reel->comments()->create([
            'user_id' => $author->id,
            'body' => $body,
        ]);

        $reel->increment('comments_count');

        return $comment->fresh(['author']);
    }

    /**
     * score = 0.40 * completion + 0.25 * engagement + 0.20 * recency
     *       + 0.15 * creator boost (new / under-exposed creators)
     */
    private function rankingScore(Reel $reel): float
    {
        $completion = (float) $reel->completion_rate;

        $engagement = min(1.0, log1p(
            ($reel->likes_count * 1.0) + ($reel->comments_count * 2.0) + ($reel->shares_count * 3.0)
        ) / log1p(1000));

        $hoursOld = max(1.0, $reel->created_at?->diffInHours(now()) ?: 1.0);
        $recency = exp(-$hoursOld / 48);

        // Creators with few reels get a bounded boost so new voices surface.
        $creatorReels = max(1, $reel->creator?->reels()->count() ?? 1);
        $creatorBoost = $creatorReels <= 5 ? 1.0 : max(0.0, 1.0 - ($creatorReels / 50));

        $trendingAudio = $reel->audio?->is_trending ? 0.15 : 0.0;

        return round(
            (0.40 * $completion)
            + (0.25 * $engagement)
            + (0.20 * $recency)
            + (0.15 * $creatorBoost)
            + $trendingAudio,
            6
        );
    }
}
