<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Event>
 */
final class EventFactory extends Factory
{
    protected $model = \App\Models\Event::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $starts = now()->addDays(fake()->numberBetween(1, 60))->setHour(fake()->numberBetween(9, 21));

        return [
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'privacy' => fake()->randomElement(['public', 'public', 'private']),
            'starts_at' => $starts,
            'ends_at' => (clone $starts)->addHours(fake()->numberBetween(1, 5)),
            'timezone' => 'UTC',
            'location_name' => fake()->optional(0.7)->city(),
            'latitude' => fake()->optional(0.7)->latitude(),
            'longitude' => fake()->optional(0.7)->longitude(),
            'is_online' => false,
            'going_count' => 0,
            'interested_count' => 0,
            'invited_count' => 0,
        ];
    }
}
