<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PostBackground;
use Illuminate\Database\Seeder;

final class PostBackgroundSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PostBackground::DEFAULTS as $index => $background) {
            PostBackground::query()->updateOrCreate(
                ['slug' => $background['slug']],
                array_merge($background, ['position' => $index + 1])
            );
        }
    }
}
