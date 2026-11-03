<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Comment>
 */
final class CommentFactory extends Factory
{
    protected $model = \App\Models\Comment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'body' => fake()->sentence(fake()->numberBetween(4, 25)),
            'depth' => 0,
            'path' => null,
            'likes_count' => 0,
            'replies_count' => 0,
        ];
    }
}
