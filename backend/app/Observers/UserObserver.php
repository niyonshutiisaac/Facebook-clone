<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;

final class UserObserver
{
    /**
     * A brand new account gets the rows its relations expect, so callers never
     * have to null-check `profile` or `settings`.
     */
    public function created(User $user): void
    {
        if ($user->profile === null) {
            $user->profile()->create();
        }

        if ($user->settings === null) {
            $user->settings()->create([
                'theme' => 'system',
                'language' => $user->locale,
            ]);
        }
    }
}
