<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Story;

final class StoryObserver
{
    public function creating(Story $story): void
    {
        $story->expires_at ??= now()->addHours(24);
    }

    public function deleted(Story $story): void
    {
        $story->media()->delete();
        $story->stickers()->delete();
    }
}
