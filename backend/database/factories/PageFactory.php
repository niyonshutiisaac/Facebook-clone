<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Page>
 */
final class PageFactory extends Factory
{
    protected $model = \App\Models\Page::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name.'-'.fake()->unique()->numberBetween(100, 99999)),
            'category' => fake()->randomElement(['Local Business', 'Community', 'Brand', 'Artist', 'Public Figure']),
            'username' => fake()->unique()->userName(),
            'description' => fake()->sentence(),
            'followers_count' => fake()->numberBetween(0, 50000),
            'likes_count' => fake()->numberBetween(0, 40000),
            'is_published' => true,
        ];
    }
}
