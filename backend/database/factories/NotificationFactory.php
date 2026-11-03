<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Notification>
 */
final class NotificationFactory extends Factory
{
    protected $model = \App\Models\Notification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => NotificationType::PostLiked,
            'data' => [],
            'group_count' => 1,
            'read_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn (): array => ['read_at' => now()]);
    }
}
