<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Message>
 */
final class MessageFactory extends Factory
{
    protected $model = \App\Models\Message::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => MessageType::Text,
            'body' => fake()->sentence(fake()->numberBetween(2, 20)),
            'delivery_status' => MessageDeliveryStatus::Sent,
        ];
    }

    public function read(): static
    {
        return $this->state(fn (): array => ['delivery_status' => MessageDeliveryStatus::Read]);
    }

    public function emojiOnly(): static
    {
        return $this->state(fn (): array => ['body' => fake()->randomElement(['😂', '❤️', '👍', '🔥'])]);
    }
}
