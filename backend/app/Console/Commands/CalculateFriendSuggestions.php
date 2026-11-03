<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Social\FriendSuggestionService;
use Illuminate\Console\Command;

final class CalculateFriendSuggestions extends Command
{
    protected $signature = 'friends:calculate-suggestions
                            {--limit=200 : How many users to process}
                            {--user= : Recalculate for one specific user}';

    protected $description = 'Recompute "People you may know" suggestions from the social graph';

    public function handle(FriendSuggestionService $suggestions): int
    {
        $userId = $this->option('user');

        if ($userId !== null) {
            $user = User::query()->findOrFail($userId);
            $count = $suggestions->rebuildFor($user);

            $this->info(sprintf('%d suggestions for %s.', $count, $user->name()));

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $total = 0;
        $processed = 0;

        User::query()
            ->active()
            ->where('last_seen_at', '>=', now()->subDays(30))
            ->orderByDesc('last_seen_at')
            ->limit($limit)
            ->get()
            ->each(function (User $user) use ($suggestions, &$total, &$processed): void {
                $total += $suggestions->rebuildFor($user);
                $processed++;
            });

        $this->info(sprintf('Rebuilt %d suggestions across %d users.', $total, $processed));

        return self::SUCCESS;
    }
}
