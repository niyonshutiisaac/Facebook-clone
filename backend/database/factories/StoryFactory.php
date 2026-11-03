<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Story>
 */
final class StoryFactory extends Factory
{
    protected $model = \App\Models\Story::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'photo',
            'audience' => 'friends',
            'duration' => 5,
            'expires_at' => now()->addHours(fake()->numberBetween(1, 24)),
            'views_count' => 0,
            'reactions_count' => 0,
            'replies_count' => 0,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subHour()]);
    }
}
