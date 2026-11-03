<?php

declare(strict_types=1);

namespace App\Services\Feed;

use App\Enums\PostType;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Intrinsic quality of a post, independent of who is looking at it.
 *
 * Signals:
 *  - media presence and count (a well-composed album beats a bare link)
 *  - text length, with a sweet spot rather than "longer is better"
 *  - presence of a link preview (structured content beats bare URLs)
 *  - poll / feeling / check-in metadata (intentional posts, not drive-bys)
 *  - explicit penalty for clickbait-shaped ALL CAPS text
 */
final class ContentQualityService
{
    /** Ideal post length in characters; score tapers off on both sides. */
    private const int IDEAL_LENGTH = 480;

    private const int LENGTH_TOLERANCE = 900;

    public function score(Post $post): float
    {
        $type = $post->typeEnum();

        $media = $this->mediaScore($post);
        $text = $this->textScore($post);
        $structure = $this->structureScore($post);
        $penalty = $this->penalties($post);

        $score = (0.34 * $media)
            + (0.30 * $text)
            + (0.36 * $structure)
            + (0.10 * $type->weight() / 1.5);

        return round(min(1.0, max(0.0, $score - $penalty)), 4);
    }

    /**
     * How much the viewer historically engages with this kind of post, 0..1.
     *
     * Falls back to a neutral 0.5 when there is not enough history, which is
     * deliberately above zero so new users still get a varied feed.
     */
    public function viewerTypeAffinity(User $viewer, PostType $type): float
    {
        $key = sprintf('affinity:types:%s', $viewer->id);

        /** @var array<string, float> $map */
        $map = Cache::remember($key, 1800, function () use ($viewer): array {
            $totals = [];
            $sum = 0.0;

            foreach (PostType::cases() as $case) {
                $value = (float) \App\Models\FeedInteractionLog::query()
                    ->where('user_id', $viewer->id)
                    ->where('meta->post_type', $case->value)
                    ->whereIn('action', ['like', 'comment', 'share', 'dwell', 'click'])
                    ->count();

                $totals[$case->value] = $value;
                $sum += $value;
            }

            if ($sum <= 0) {
                return array_fill_keys(array_keys($totals), 0.5);
            }

            return array_map(
                static fn (float $v): float => round(max(0.05, min(1.0, $v / $sum * count($totals))), 4),
                $totals
            );
        });

        return $map[$type->value] ?? 0.5;
    }

    private function mediaScore(Post $post): float
    {
        $count = $post->media_count ?? null;

        if ($count === null && $post->relationLoaded('media')) {
            $count = $post->media->count();
        }

        $count = (int) ($count ?? 0);

        return match (true) {
            $count >= 4 => 1.0,
            $count === 3 => 0.9,
            $count === 2 => 0.8,
            $count === 1 => 0.7,
            default => 0.0,
        };
    }

    /**
     * Gaussian-ish taper around the ideal length: very short "yolo" posts and
     * 5,000 character walls both score lower than a well-written paragraph.
     */
    private function textScore(Post $post): float
    {
        $length = mb_strlen((string) $post->body);

        if ($length === 0) {
            // No text at all is fine for a photo post, weak otherwise.
            return in_array($post->typeEnum(), [PostType::Photo, PostType::Video, PostType::Album, PostType::Gif], true)
                ? 0.4
                : 0.05;
        }

        $delta = abs($length - self::IDEAL_LENGTH);

        return round(max(0.15, 1.0 - ($delta / self::LENGTH_TOLERANCE)), 4);
    }

    private function structureScore(Post $post): float
    {
        $score = 0.5;

        if ($post->link !== null && $post->link->preview !== null) {
            $score += 0.2;
        }

        if ($post->background !== null) {
            $score += 0.05;
        }

        if ($post->feeling !== null || $post->activity !== null) {
            $score += 0.08;
        }

        if ($post->location_name !== null) {
            $score += 0.08;
        }

        if ($post->poll !== null) {
            $score += 0.15;
        }

        if ($post->typeEnum() === PostType::Shared) {
            // Shares add context; a share with a comment is a real contribution.
            $score += $post->share_comment !== null ? 0.2 : 0.05;
        }

        return min(1.0, $score);
    }

    /**
     * @return float 0..1 penalty subtracted from the final score
     */
    private function penalties(Post $post): float
    {
        $body = (string) $post->body;

        if ($body === '') {
            return 0.0;
        }

        $letters = preg_replace('/[^[:alpha:]]/u', '', $body) ?? '';

        if (mb_strlen($letters) < 20) {
            return 0.0;
        }

        $upper = mb_strlen(preg_replace('/[^\p{Lu}]/u', '', $letters) ?? '');
        $ratio = $upper / max(1, mb_strlen($letters));

        return $ratio > 0.6 ? 0.25 : 0.0;
    }
}
