<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReactionType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Reaction>
 */
final class ReactionFactory extends Factory
{
    protected $model = \App\Models\Reaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ReactionType::Like,
        ];
    }
}
