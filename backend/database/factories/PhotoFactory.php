<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Photo>
 */
final class PhotoFactory extends Factory
{
    protected $model = \App\Models\Photo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'path' => 'demo/photo-'.fake()->numberBetween(1, 20).'.jpg',
            'disk' => 'public',
            'caption' => fake()->optional(0.4)->sentence(),
            'audience' => 'public',
            'width' => 1600,
            'height' => 1600,
        ];
    }
}
