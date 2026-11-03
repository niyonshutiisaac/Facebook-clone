<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Connects the demo users into a plausible social graph.
 *
 * Ava is fully connected to everyone; the rest are wired with roughly 60%
 * density so "mutual friends" and "people you may know" produce interesting
 * numbers rather than either zero or N.
 */
final class FriendshipSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->orderBy('email')->get();

        if ($users->count() < 2) {
            return;
        }

        $ava = $users->firstWhere('username', 'avachen');

        foreach ($users as $a) {
            foreach ($users as $b) {
                if ($a->id === $b->id) {
                    continue;
                }

                $shouldConnect = $ava !== null
                    && ($a->id === $ava->id || $b->id === $ava->id)
                    ? true
                    : $a->id < $b->id && fake()->boolean(60);

                if (! $shouldConnect) {
                    continue;
                }

                app(\App\Services\Social\FriendshipService::class)->acceptDirectly($a, $b);
            }
        }

        // A couple of pending inbound requests so the badge is populated.
        $pending = $users->where('username', '!=', 'avachen')->take(3);

        foreach ($pending as $sender) {
            if ($ava === null) {
                continue;
            }

            app(\App\Services\Social\FriendshipService::class)->sendRequest($sender, $ava, 'Hey! Long time no see.');
        }

        $this->command?->info('Seeded friendships and 3 pending friend requests.');
    }
}
