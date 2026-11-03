<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Friendship>
 */
final class FriendshipFactory extends Factory
{
    protected $model = \App\Models\Friendship::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'closeness_score' => fake()->randomFloat(4, 0.05, 1.0),
            'is_following' => true,
            'is_close_friend' => false,
            'is_restricted' => false,
            'is_blocked' => false,
            'is_messenger_favorite' => false,
            'interaction_count' => fake()->numberBetween(0, 400),
            'created_at' => now()->subDays(fake()->numberBetween(1, 900)),
        ];
    }
}
