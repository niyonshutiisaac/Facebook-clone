<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Group>
 */
final class GroupFactory extends Factory
{
    protected $model = \App\Models\Group::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name.'-'.fake()->unique()->numberBetween(100, 99999)),
            'description' => fake()->paragraph(),
            'privacy' => fake()->randomElement(['public', 'public', 'private_visible']),
            'type' => 'general',
            'members_count' => 0,
            'posts_count' => 0,
            'pending_members_count' => 0,
            'requires_post_approval' => false,
        ];
    }

    public function private(): static
    {
        return $this->state(fn (): array => ['privacy' => 'private_hidden']);
    }
}
