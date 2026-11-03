<?php

declare(strict_types=1);

use App\Console\Commands\CalculateFriendSuggestions;
use App\Console\Commands\CleanupExpiredStories;
use App\Console\Commands\PruneActivityLogs;
use App\Console\Commands\RefreshNewsFeedCache;
use App\Console\Commands\UpdateFeedRankingScores;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
|
| `php artisan schedule:work` (the `scheduler` service in docker-compose.yml)
| runs these. Overlapping jobs are prevented with `withoutOverlapping` so a
| slow run never stacks on top of the previous one.
|
*/

Artisan::command('inspire', function (): void {
    /** @var \Illuminate\Console\Command $this */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Feed ranking inputs are recomputed every fifteen minutes: affinity scores,
// engagement velocity and the per-post quality score.
Schedule::command(UpdateFeedRankingScores::class)
    ->everyFifteenMinutes()
    ->withoutOverlapping(10)
    ->runInBackground();

// Warm the feed for users active in the last hour so the first request is a
// cache hit. Staggered by minute to spread database load.
Schedule::command(RefreshNewsFeedCache::class)
    ->everyFiveMinutes()
    ->withoutOverlapping(30)
    ->runInBackground();

// "People you may know" is expensive; nightly is plenty.
Schedule::command(CalculateFriendSuggestions::class)
    ->dailyAt('03:15')
    ->withoutOverlapping(30)
    ->runInBackground();

// Stories expire 24 hours after publication.
Schedule::command(CleanupExpiredStories::class)
    ->hourly()
    ->withoutOverlapping();

// Keep the audit table bounded (90 days of history).
Schedule::command(PruneActivityLogs::class)->dailyAt('04:00');

// Housekeeping.
Schedule::command('sanctum:prune-expired --hours=720')->daily();
Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('telescope:prune --hours=48')->daily()->when(
    fn (): bool => class_exists(\Laravel\Telescope\Telescope::class)
);

// Nightly search index sync for anything the observers missed.
Schedule::command('scout:import', ['--filter' => 'App\\\\Models\\\\User'])
    ->dailyAt('04:30')
    ->withoutOverlapping();
