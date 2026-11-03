<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Story;
use App\Models\User;
use Illuminate\Database\Seeder;

final class StorySeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->get();

        foreach ($users as $user) {
            if (! fake()->boolean(70)) {
                continue;
            }

            $stories = \App\Models\Story::factory()
                ->count(random_int(1, 3))
                ->create(['user_id' => $user->id, 'audience' => 'friends']);

            foreach ($stories as $story) {
                /** @var Story $story */
                $story->media()->create([
                    'type' => 'image',
                    'path' => 'demo/story-'.fake()->numberBetween(1, 8).'.jpg',
                    'disk' => 'public',
                    'mime_type' => 'image/jpeg',
                    'width' => 1080,
                    'height' => 1920,
                ]);

                // Half the tray has already been watched.
                foreach ($users->where('id', '!=', $user->id)->random(2) as $viewer) {
                    $story->viewers()->create([
                        'viewer_id' => $viewer->id,
                        'viewed_at' => now()->subMinutes(random_int(5, 600)),
                    ]);
                }
            }
        }

        $this->command?->info('Seeded story trays.');
    }
}
