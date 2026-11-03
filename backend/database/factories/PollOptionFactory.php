<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\PollOption>
 */
final class PollOptionFactory extends Factory
{
    protected $model = \App\Models\PollOption::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label' => ucfirst(fake()->words(fake()->numberBetween(1, 3), true)),
            'position' => 0,
            'votes_count' => 0,
        ];
    }
}
