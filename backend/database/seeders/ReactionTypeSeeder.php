<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The reaction palette, in display order.
 *
 * Seeded rather than hard-coded so labels can be localised per locale row and
 * so admins can disable a reaction (e.g. "angry" for brand-safe environments).
 */
final class ReactionTypeSeeder extends Seeder
{
    /** @var list<array{name: string, slug: string, label: string, emoji: string, color: string, position: int}> */
    private const array REACTIONS = [
        ['name' => 'Like', 'slug' => 'like', 'label' => 'Like', 'emoji' => '👍', 'color' => '#3B5998', 'position' => 1],
        ['name' => 'Love', 'slug' => 'love', 'label' => 'Love', 'emoji' => '❤️', 'color' => '#F33E58', 'position' => 2],
        ['name' => 'Care', 'slug' => 'care', 'label' => 'Care', 'emoji' => '🥰', 'color' => '#F7B125', 'position' => 3],
        ['name' => 'Haha', 'slug' => 'haha', 'label' => 'Haha', 'emoji' => '😂', 'color' => '#F7B125', 'position' => 4],
        ['name' => 'Wow', 'slug' => 'wow', 'label' => 'Wow', 'emoji' => '😮', 'color' => '#F7B125', 'position' => 5],
        ['name' => 'Sad', 'slug' => 'sad', 'label' => 'Sad', 'emoji' => '😢', 'color' => '#F7B125', 'position' => 6],
        ['name' => 'Angry', 'slug' => 'angry', 'label' => 'Angry', 'emoji' => '😡', 'color' => '#E9710F', 'position' => 7],
    ];

    public function run(): void
    {
        $now = now();

        foreach (self::REACTIONS as $reaction) {
            DB::table('reaction_types')->updateOrInsert(
                ['slug' => $reaction['slug']],
                array_merge($reaction, ['is_active' => true, 'updated_at' => $now, 'created_at' => $now])
            );
        }
    }
}
