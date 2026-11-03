<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class NotificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function reacting_to_someones_post_notifies_them(): void
    {
        [$author, $reactor] = User::factory()->withProfile()->count(2)->create()->all();

        $post = Post::factory()->create(['user_id' => $author->id]);

        $this->actingAs($reactor)
            ->putJson("/api/post/{$post->id}/reactions", ['type' => 'love'])
            ->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $author->id,
            'type' => 'post_liked',
        ]);
    }

    #[Test]
    public function you_are_never_notified_about_your_own_actions(): void
    {
        $author = User::factory()->withProfile()->create();
        $post = Post::factory()->create(['user_id' => $author->id]);

        $this->actingAs($author)
            ->putJson("/api/post/{$post->id}/reactions", ['type' => 'love'])
            ->assertOk();

        $this->assertDatabaseCount('notifications', 0);
    }

    #[Test]
    public function similar_notifications_are_aggregated_within_the_window(): void
    {
        $author = User::factory()->withProfile()->create();
        $reactors = User::factory()->withProfile()->count(4)->create();

        $post = Post::factory()->create(['user_id' => $author->id]);

        foreach ($reactors as $reactor) {
            $this->actingAs($reactor)
                ->putJson("/api/post/{$post->id}/reactions", ['type' => 'like']);
        }

        $notification = \App\Models\Notification::query()
            ->where('user_id', $author->id)
            ->first();

        $this->assertEquals(4, $notification->group_count);
        $this->assertStringContainsString('others', (string) $notification->text);
    }

    #[Test]
    public function marking_all_as_read_clears_the_counter(): void
    {
        $author = User::factory()->withProfile()->create();
        $reactor = User::factory()->withProfile()->create();

        $post = Post::factory()->create(['user_id' => $author->id]);

        $this->actingAs($reactor)
            ->putJson("/api/post/{$post->id}/reactions", ['type' => 'wow']);

        $this->actingAs($author)
            ->getJson('/api/notifications/unread-count')
            ->assertJsonPath('count', 1);

        $this->actingAs($author)->putJson('/api/notifications/read-all')->assertOk();

        $this->actingAs($author)
            ->getJson('/api/notifications/unread-count')
            ->assertJsonPath('count', 0);
    }
}
