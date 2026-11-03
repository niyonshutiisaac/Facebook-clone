<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Poll>
 */
final class PollFactory extends Factory
{
    protected $model = \App\Models\Poll::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question' => rtrim(fake()->sentence(6), '.'),
            'total_votes' => 0,
            'voters_count' => 0,
            'allows_multiple' => false,
            'closes_at' => fake()->optional(0.3)->dateTimeBetween('+1 day', '+10 days'),
        ];
    }

    /**
     * Create the poll with N options.
     */
    public function withOptions(int $count = 2): static
    {
        return $this->afterCreating(function (\App\Models\Poll $poll) use ($count): void {
            \App\Models\PollOption::factory()->count($count)->create(['poll_id' => $poll->id]);
        });
    }
}
