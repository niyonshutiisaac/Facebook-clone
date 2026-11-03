<?php

declare(strict_types=1);

namespace App\Services\Feed;

use App\Models\FeedInteractionLog;
use App\Models\Post;
use App\Models\User;
use App\Models\UserInteraction;
use Illuminate\Support\Facades\DB;

/**
 * Writes the raw interaction stream and rolls it up into `user_interactions`.
 *
 * Writes are cheap and never throw into the request path: a failed tracking
 * write degrades ranking slightly, so it is logged and swallowed rather than
 * bubbling a 500 to the user who just scrolled past a post.
 */
final class InteractionTrackingService
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function record(
        User $user,
        string $action,
        ?Post $post = null,
        ?string $subjectId = null,
        ?string $subjectType = null,
        int $durationMs = 0,
        array $meta = [],
    ): void {
        try {
            DB::transaction(function () use ($user, $action, $post, $subjectId, $subjectType, $durationMs, $meta): void {
                $targetId = $subjectId ?? $post?->user_id;
                $targetType = $subjectType ?? ($post !== null ? 'user' : null);

                FeedInteractionLog::create([
                    'user_id' => $user->id,
                    'post_id' => $post?->id,
                    'subject_id' => $targetId,
                    'subject_type' => $targetType,
                    'action' => $action,
                    'weight' => FeedInteractionLog::weightFor($action),
                    'duration_ms' => $durationMs,
                    'meta' => $meta,
                    'occurred_at' => now(),
                ]);

                if ($targetId !== null && $targetType === 'user' && $targetId !== $user->id) {
                    $this->rollUp($user->id, $targetId, $action, $durationMs);
                }

                if ($post !== null) {
                    $this->bumpPostCounters($post, $action);
                }
            }, 3);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Incrementally maintains the aggregated row so reads stay O(1).
     */
    private function rollUp(string $userId, string $targetId, string $action, int $durationMs): void
    {
        $interaction = UserInteraction::query()->firstOrCreate(
            ['user_id' => $userId, 'target_user_id' => $targetId],
            ['affinity_score' => 0]
        );

        $updates = ['last_interaction_at' => now()];

        switch ($action) {
            case 'like':
                $updates['reactions_given'] = DB::raw('reactions_given + 1');
                break;
            case 'comment':
                $updates['comments_written'] = DB::raw('comments_written + 1');
                break;
            case 'profile_view':
                $updates['profile_views'] = DB::raw('profile_views + 1');
                break;
            case 'view':
                $updates['posts_viewed'] = DB::raw('posts_viewed + 1');
                break;
            case 'dwell':
                $updates['dwell_ms'] = DB::raw('dwell_ms + '.(int) $durationMs);
                break;
        }

        $interaction->forceFill($updates)->save();
    }

    private function bumpPostCounters(Post $post, string $action): void
    {
        if ($action === 'view') {
            $post->withoutEvents(fn () => $post->increment('views_count'));
        }
    }
}
