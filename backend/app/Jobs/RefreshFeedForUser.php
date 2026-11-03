<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\Feed\FeedCacheService;
use App\Services\Feed\NewsFeedService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class RefreshFeedForUser implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public readonly User $user)
    {
        $this->onQueue('feed');
    }

    public function handle(NewsFeedService $feed, FeedCacheService $cache): void
    {
        // Only one worker may rebuild a given feed: everyone else skips instead
        // of repeating the same expensive work.
        if (! $cache->lockForRebuild($this->user)) {
            return;
        }

        try {
            $cache->rebuild($this->user, $feed->candidates($this->user));
        } finally {
            $cache->unlock($this->user);
        }
    }
}
