<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Reference data first: everything else depends on it existing.
            ReactionTypeSeeder::class,
            PostBackgroundSeeder::class,
            ReportReasonSeeder::class,

            // Demo content.
            DemoUserSeeder::class,
            FriendshipSeeder::class,
            PostSeeder::class,
            CommentSeeder::class,
            StorySeeder::class,
            ConversationSeeder::class,
            GroupSeeder::class,
        ]);
    }
}
