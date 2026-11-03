<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RefreshFeedForUser;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Warms the feed cache for users active in the last hour, so their next
 * request is a Redis hit rather than a full candidate scan + ranking pass.
 */
final class RefreshNewsFeedCache extends Command
{
    protected $signature = 'feed:refresh-cache
                            {--minutes=60 : Refresh feeds for users active within this window}
                            {--limit=200 : Maximum number of users per run}
                            {--user= : Refresh one specific user id}';

    protected $description = 'Pre-compute ranked news feeds for recently active users';

    public function handle(): int
    {
        $userId = $this->option('user');

        if ($userId !== null) {
            $user = User::query()->find($userId);

            if ($user === null) {
                $this->error(sprintf('No user found with id [%s].', $userId));

                return self::FAILURE;
            }

            RefreshFeedForUser::dispatch($user);

            $this->info(sprintf('Dispatched a feed refresh for %s.', $user->name()));

            return self::SUCCESS;
        }

        $minutes = max(1, (int) $this->option('minutes'));
        $limit = max(1, (int) $this->option('limit'));

        $users = User::query()
            ->active()
            ->where('last_seen_at', '>=', now()->subMinutes($minutes))
            ->orderByDesc('last_seen_at')
            ->limit($limit)
            ->get();

        foreach ($users as $user) {
            RefreshFeedForUser::dispatch($user);
        }

        $this->info(sprintf('Dispatched feed refreshes for %d users.', $users->count()));

        return self::SUCCESS;
    }
}
