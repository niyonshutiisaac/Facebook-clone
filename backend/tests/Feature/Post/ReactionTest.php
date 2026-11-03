<?php

declare(strict_types=1);

namespace Tests\Feature\Post;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReactionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function reacting_twice_with_the_same_type_removes_the_reaction(): void
    {
        $author = User::factory()->withProfile()->create();
        $reactor = User::factory()->withProfile()->create();

        $post = Post::factory()->create(['user_id' => $author->id]);

        $this->actingAs($reactor)
            ->putJson("/api/post/{$post->id}/reactions", ['type' => 'like'])
            ->assertOk()
            ->assertJsonPath('reaction', 'like');

        $this->assertEquals(1, (int) $post->fresh()->reactions_count);

        $this->actingAs($reactor)
            ->putJson("/api/post/{$post->id}/reactions", ['type' => 'like'])
            ->assertOk();

        $this->assertEquals(0, (int) $post->fresh()->reactions_count);
    }

    #[Test]
    public function changing_reaction_swaps_the_type_instead_of_adding_a_second_one(): void
    {
        $author = User::factory()->withProfile()->create();
        $reactor = User::factory()->withProfile()->create();

        $post = Post::factory()->create(['user_id' => $author->id]);

        $this->actingAs($reactor)->putJson("/api/post/{$post->id}/reactions", ['type' => 'like']);
        $this->actingAs($reactor)->putJson("/api/post/{$post->id}/reactions", ['type' => 'love'])->assertOk();

        $this->assertEquals(1, (int) $post->fresh()->reactions_count);
        $this->assertDatabaseHas('reactions', [
            'reactable_id' => $post->id,
            'user_id' => $reactor->id,
            'type' => 'love',
        ]);
    }

    #[Test]
    public function reactions_are_polymorphic_and_also_work_on_comments(): void
    {
        $author = User::factory()->withProfile()->create();
        $post = Post::factory()->create(['user_id' => $author->id]);
        $comment = Comment::factory()->create(['post_id' => $post->id, 'user_id' => $author->id]);

        $this->actingAs($author)
            ->putJson("/api/comment/{$comment->id}/reactions", ['type' => 'haha'])
            ->assertOk();

        $this->assertDatabaseHas('reactions', [
            'reactable_type' => 'comment',
            'reactable_id' => $comment->id,
        ]);
    }

    #[Test]
    public function an_invalid_reaction_type_is_rejected(): void
    {
        $author = User::factory()->withProfile()->create();
        $post = Post::factory()->create(['user_id' => $author->id]);

        $this->actingAs($author)
            ->putJson("/api/post/{$post->id}/reactions", ['type' => 'confused'])
            ->assertUnprocessable();
    }
}
