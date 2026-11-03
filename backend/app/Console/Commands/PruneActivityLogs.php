<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class PruneActivityLogs extends Command
{
    protected $signature = 'activity:prune {--days=90 : Keep this many days of history}';

    protected $description = 'Delete activity log rows older than the retention window';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        $deleted = DB::table('activity_log')
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info(sprintf('Deleted %d activity log rows older than %d days.', $deleted, $days));

        return self::SUCCESS;
    }
}
