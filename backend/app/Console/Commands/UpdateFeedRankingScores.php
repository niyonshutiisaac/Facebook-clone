<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\Feed\AffinityScoreService;
use App\Services\Feed\ContentQualityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recomputes the ranking inputs the feed reads at request time.
 *
 * Three passes:
 *   1. closeness_score on every friendship row (affinity between two people)
 *   2. quality_score + engagement_velocity on recent posts
 *   3. invalidation of the cached affinity maps
 *
 * Doing this offline is what keeps feed requests to a single indexed read.
 */
final class UpdateFeedRankingScores extends Command
{
    protected $signature = 'feed:update-scores
                            {--days=2 : How far back to recompute post scores}
                            {--chunk=500 : Rows per chunk}';

    protected $description = 'Recompute affinity, quality and engagement inputs for the news feed ranking model';

    public function __construct(
        private readonly AffinityScoreService $affinity,
        private readonly ContentQualityService $quality,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $chunk = max(50, (int) $this->option('chunk'));

        $this->info('Updating friendship closeness scores…');
        $friendships = $this->affinity->refreshClosenessScores($chunk);
        $this->line(sprintf('  <info>%d</info> friendship rows updated.', $friendships));

        $this->info('Updating post quality and engagement velocity…');

        $posts = 0;

        Post::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('id')
            ->chunkById($chunk, function ($batch) use (&$posts): void {
                foreach ($batch as $post) {
                    /** @var Post $post */
                    $engagements = ((int) $post->reactions_count)
                        + ((int) $post->comments_count * 2)
                        + ((int) $post->shares_count * 3);

                    $minutesLive = max(1.0, $post->created_at?->diffInMinutes(now()) ?: 1.0);
                    $windowMinutes = min($minutesLive, 60.0);

                    DB::table('posts')->where('id', $post->id)->update([
                        'quality_score' => $this->quality->score($post),
                        'engagement_velocity' => round($engagements / $windowMinutes, 4),
                    ]);

                    $posts++;
                }
            });

        $this->line(sprintf('  <info>%d</info> posts rescored.', $posts));

        $this->info('Invalidating cached affinity maps…');

        $invalidated = 0;

        \App\Models\User::query()->orderBy('id')->chunkById($chunk, function ($batch) use (&$invalidated): void {
            foreach ($batch as $user) {
                /** @var \App\Models\User $user */
                $this->affinity->forget($user);
                $invalidated++;
            }
        });

        $this->line(sprintf('  <info>%d</info> affinity maps invalidated.', $invalidated));

        return self::SUCCESS;
    }
}
