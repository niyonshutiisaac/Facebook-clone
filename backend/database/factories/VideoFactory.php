<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Video>
 */
final class VideoFactory extends Factory
{
    protected $model = \App\Models\Video::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'path' => 'demo/video-'.fake()->numberBetween(1, 6).'.mp4',
            'disk' => 'public',
            'thumbnails' => [],
            'available_qualities' => ['720p'],
            'mime_type' => 'video/mp4',
            'duration' => fake()->numberBetween(15, 600),
            'width' => 1280,
            'height' => 720,
            'size' => fake()->numberBetween(2_000_000, 120_000_000),
            'audience' => 'public',
            'status' => 'ready',
            'views_count' => 0,
        ];
    }
}
