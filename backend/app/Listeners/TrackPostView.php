<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;

/**
 * Refreshes "last seen" on login and prunes stale presence rows left behind by
 * clients that disconnected without a clean goodbye.
 */
final class TrackPostView
{
    public function handle(Login $event): void
    {
        /** @var \App\Models\User|null $user */
        $user = $event->user;

        if ($user !== null) {
            $user->forceFill(['is_online' => true, 'last_seen_at' => now()])->saveQuietly();
        }

        DB::table('user_sessions')
            ->where('last_activity_at', '<', now()->subDays(30))
            ->delete();
    }
}
