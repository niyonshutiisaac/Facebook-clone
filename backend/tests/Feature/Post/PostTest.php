<?php

declare(strict_types=1);

namespace Tests\Feature\Post;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PostTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_authenticated_user_can_create_a_text_post(): void
    {
        $user = User::factory()->withProfile()->create();

        $this->actingAs($user)
            ->postJson('/api/posts', [
                'body' => 'Shipping beats perfect.',
                'type' => 'text',
                'audience' => 'friends',
            ])
            ->assertCreated()
            ->assertJsonPath('type', 'text')
            ->assertJsonPath('body', 'Shipping beats perfect.');

        $this->assertDatabaseHas('posts', ['user_id' => $user->id, 'body' => 'Shipping beats perfect.']);
    }

    #[Test]
    public function a_post_can_carry_media(): void
    {
        Storage::fake('public');

        $user = User::factory()->withProfile()->create();

        $this->actingAs($user)
            ->postJson('/api/posts', [
                'type' => 'photo',
                'audience' => 'public',
                'media' => [UploadedFile::fake()->image('sunset.jpg', 1600, 900)],
            ])
            ->assertCreated();

        $this->assertDatabaseCount('post_media', 1);
    }

    #[Test]
    public function only_the_author_may_edit_a_post(): void
    {
        $author = User::factory()->withProfile()->create();
        $stranger = User::factory()->withProfile()->create();

        $post = Post::factory()->create(['user_id' => $author->id]);

        $this->actingAs($stranger)
            ->putJson("/api/posts/{$post->id}", ['body' => 'Hijacked.'])
            ->assertForbidden();

        $this->actingAs($author)
            ->putJson("/api/posts/{$post->id}", ['body' => 'Edited.'])
            ->assertOk()
            ->assertJsonPath('body', 'Edited.');

        $this->assertDatabaseHas('post_edit_history', ['post_id' => $post->id]);
    }

    #[Test]
    public function deleting_a_post_soft_deletes_it(): void
    {
        $author = User::factory()->withProfile()->create();
        $post = Post::factory()->create(['user_id' => $author->id]);

        $this->actingAs($author)->deleteJson("/api/posts/{$post->id}")->assertOk();

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }

    #[Test]
    public function a_poll_post_validates_its_options(): void
    {
        $user = User::factory()->withProfile()->create();

        $this->actingAs($user)
            ->postJson('/api/posts', [
                'type' => 'poll',
                'poll_question' => 'Correct amount of garlic?',
                'poll_options' => ['One'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('poll_options');
    }

    #[Test]
    public function sharing_another_post_increments_the_share_counter(): void
    {
        $author = User::factory()->withProfile()->create();
        $sharer = User::factory()->withProfile()->create();

        $post = Post::factory()->public()->create(['user_id' => $author->id]);

        $this->actingAs($sharer)
            ->postJson("/api/posts/{$post->id}/share", ['body' => 'This.'])
            ->assertCreated()
            ->assertJsonPath('shared_post.id', $post->id);

        $this->assertEquals(1, (int) $post->fresh()->shares_count);
    }

    #[Test]
    public function the_author_can_change_the_audience_without_editing_the_body(): void
    {
        $author = User::factory()->withProfile()->create();
        $post = Post::factory()->create(['user_id' => $author->id, 'audience' => 'friends']);

        $this->actingAs($author)
            ->putJson("/api/posts/{$post->id}/audience", ['audience' => 'only_me'])
            ->assertOk()
            ->assertJsonPath('audience', 'only_me');
    }
}
