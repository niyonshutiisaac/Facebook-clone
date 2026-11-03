<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Reel>
 */
final class ReelFactory extends Factory
{
    protected $model = \App\Models\Reel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'path' => 'demo/reel-'.fake()->numberBetween(1, 8).'.mp4',
            'disk' => 'public',
            'mime_type' => 'video/mp4',
            'size' => fake()->numberBetween(2_000_000, 60_000_000),
            'caption' => fake()->optional(0.7)->sentence(),
            'audience' => 'public',
            'duration' => fake()->numberBetween(9, 90),
            'width' => 1080,
            'height' => 1920,
            'status' => 'ready',
            'allows_remix' => true,
            'ranking_score' => fake()->randomFloat(6, 0, 1),
            'completion_rate' => fake()->randomFloat(4, 0.1, 1),
            'views_count' => 0,
            'likes_count' => 0,
            'comments_count' => 0,
            'shares_count' => 0,
        ];
    }
}
