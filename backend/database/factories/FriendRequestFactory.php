<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FriendRequestStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\FriendRequest>
 */
final class FriendRequestFactory extends Factory
{
    protected $model = \App\Models\FriendRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => FriendRequestStatus::Pending,
            'message' => fake()->optional(0.3)->sentence(),
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => [
            'status' => FriendRequestStatus::Accepted,
            'responded_at' => now(),
        ]);
    }
}
