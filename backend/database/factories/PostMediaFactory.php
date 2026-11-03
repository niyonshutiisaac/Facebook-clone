<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\PostMedia>
 */
final class PostMediaFactory extends Factory
{
    protected $model = \App\Models\PostMedia::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'image',
            'path' => 'demo/post-'.fake()->numberBetween(1, 12).'.jpg',
            'disk' => 'public',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(80_000, 4_000_000),
            'width' => 1600,
            'height' => 900,
            'position' => 0,
        ];
    }

    public function video(): static
    {
        return $this->state(fn (): array => [
            'type' => 'video',
            'path' => 'demo/video-'.fake()->numberBetween(1, 6).'.mp4',
            'mime_type' => 'video/mp4',
            'duration' => fake()->numberBetween(8, 240),
        ]);
    }
}
