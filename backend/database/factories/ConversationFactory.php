<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ConversationType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Conversation>
 */
final class ConversationFactory extends Factory
{
    protected $model = \App\Models\Conversation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ConversationType::Private,
            'is_archived' => false,
        ];
    }

    public function group(): static
    {
        return $this->state(fn (): array => [
            'type' => ConversationType::Group,
            'title' => fake()->words(3, true),
            'created_by' => \App\Models\User::factory(),
        ]);
    }
}
