<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Content\StoryService;
use Illuminate\Console\Command;

final class CleanupExpiredStories extends Command
{
    protected $signature = 'stories:cleanup
                            {--hard : Permanently delete stories archived more than 30 days ago}';

    protected $description = 'Archive stories that have passed their 24 hour expiry window';

    public function handle(StoryService $stories): int
    {
        $archived = $stories->archiveExpired();

        $this->info(sprintf('%d stories archived.', $archived));

        if ($this->option('hard')) {
            $deleted = \App\Models\Story::query()
                ->where('is_archived', true)
                ->where('expires_at', '<=', now()->subDays(30))
                ->forceDelete();

            $this->info(sprintf('%d archived stories permanently deleted.', $deleted));
        }

        return self::SUCCESS;
    }
}
