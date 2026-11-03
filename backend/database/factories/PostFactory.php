<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Audience;
use App\Enums\PostType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Post>
 */
final class PostFactory extends Factory
{
    protected $model = \App\Models\Post::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $created = now()->subMinutes(fake()->numberBetween(0, 60 * 24 * 3));

        return [
            'type' => PostType::Text,
            'body' => fake()->paragraphs(fake()->numberBetween(1, 3), true),
            'audience' => Audience::Friends,
            'is_published' => true,
            'published_at' => $created,
            'comments_enabled' => true,
            'shares_count' => 0,
            'reactions_count' => 0,
            'comments_count' => 0,
            'quality_score' => fake()->randomFloat(4, 0.1, 1.0),
            'engagement_velocity' => 0,
            'created_at' => $created,
            'updated_at' => $created,
        ];
    }

    public function withMedia(int $count = 1): static
    {
        return $this->afterCreating(function (\App\Models\Post $post) use ($count): void {
            \App\Models\PostMedia::factory()->count($count)->create(['post_id' => $post->id]);
        });
    }

    public function withPoll(): static
    {
        return $this->afterCreating(function (\App\Models\Post $post): void {
            \App\Models\Poll::factory()->create(['post_id' => $post->id]);
        });
    }

    public function public(): static
    {
        return $this->state(fn (): array => ['audience' => Audience::Public]);
    }

    public function withType(PostType $type): static
    {
        return $this->state(fn (): array => ['type' => $type]);
    }

    public function purchased(): static
    {
        return $this->state(fn (): array => ['is_published' => false, 'published_at' => null]);
    }
}
