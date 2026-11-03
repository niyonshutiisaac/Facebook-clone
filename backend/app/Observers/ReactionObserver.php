<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Reaction;

final class ReactionObserver
{
    public function created(Reaction $reaction): void
    {
        $this->recount($reaction);
    }

    public function deleted(Reaction $reaction): void
    {
        $this->recount($reaction);
    }

    private function recount(Reaction $reaction): void
    {
        $target = $reaction->reactable;

        if ($target === null) {
            return;
        }

        if (array_key_exists('reactions_count', $target->getAttributes())) {
            $target->forceFill([
                'reactions_count' => $target->reactions()->count(),
            ])->saveQuietly();
        }
    }
}
