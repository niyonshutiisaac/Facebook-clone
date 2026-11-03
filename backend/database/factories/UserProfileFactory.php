<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\UserProfile>
 */
final class UserProfileFactory extends Factory
{
    protected $model = \App\Models\UserProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'headline' => fake()->optional(0.5)->sentence(4),
            'about' => fake()->optional(0.6)->paragraph(),
            'current_city' => fake()->optional(0.7)->city(),
            'hometown' => fake()->optional(0.6)->city(),
            'website' => fake()->optional(0.2)->url(),
            'relationship_status' => fake()->optional(0.6)
                ->randomElement(['single', 'in_a_relationship', 'married', 'engaged', 'its_complicated']),
            'friends_count' => 0,
            'followers_count' => 0,
            'photos_count' => 0,
            'posts_count' => 0,
        ];
    }
}
