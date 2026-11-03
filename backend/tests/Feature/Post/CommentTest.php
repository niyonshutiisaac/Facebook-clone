<?php

declare(strict_types=1);

namespace Tests\Feature\Post;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CommentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_comment_can_be_created_and_increments_the_counter(): void
    {
        $author = User::factory()->withProfile()->create();
        $commenter = User::factory()->withProfile()->create();

        $post = Post::factory()->create(['user_id' => $author->id]);

        $this->actingAs($commenter)
            ->postJson("/api/posts/{$post->id}/comments", ['body' => 'This is excellent.'])
            ->assertCreated()
            ->assertJsonPath('body', 'This is excellent.');

        $this->assertEquals(1, (int) $post->fresh()->comments_count);
    }

    #[Test]
    public function replies_are_nested_and_capped_at_three_levels(): void
    {
        $author = User::factory()->withProfile()->create();
        $post = Post::factory()->create(['user_id' => $author->id]);

        $depthZero = $this->actingAs($author)
            ->postJson("/api/posts/{$post->id}/comments", ['body' => 'one'])
            ->json('id');

        $depthOne = $this->actingAs($author)
            ->postJson("/api/comments/{$depthZero}/replies", ['body' => 'two'])
            ->assertCreated()
            ->json('id');

        $depthTwo = $this->actingAs($author)
            ->postJson("/api/comments/{$depthOne}/replies", ['body' => 'three'])
            ->assertCreated()
            ->json('id');

        $this->actingAs($author)
            ->postJson("/api/comments/{$depthTwo}/replies", ['body' => 'four'])
            ->assertUnprocessable();
    }

    #[Test]
    public function deleting_a_comment_keeps_a_tombstone_when_it_has_replies(): void
    {
        $author = User::factory()->withProfile()->create();
        $post = Post::factory()->create(['user_id' => $author->id]);

        $parent = $this->actingAs($author)
            ->postJson("/api/posts/{$post->id}/comments", ['body' => 'parent'])
            ->json('id');

        $this->actingAs($author)
            ->postJson("/api/comments/{$parent}/replies", ['body' => 'child']);

        $this->actingAs($author)->deleteJson("/api/comments/{$parent}")->assertOk();

        $this->assertDatabaseHas('comments', [
            'id' => $parent,
            'is_deleted' => true,
        ]);
    }

    #[Test]
    public function the_thread_is_ordered_oldest_first(): void
    {
        $author = User::factory()->withProfile()->create();
        $post = Post::factory()->create(['user_id' => $author->id]);

        foreach (['first', 'second', 'third'] as $body) {
            $this->actingAs($author)->postJson("/api/posts/{$post->id}/comments", ['body' => $body]);
        }

        $bodies = $this->actingAs($author)
            ->getJson("/api/posts/{$post->id}/comments")
            ->json('*.body');

        $this->assertSame(['first', 'second', 'third'], $bodies);
    }
}
