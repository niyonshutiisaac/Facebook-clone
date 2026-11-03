<?php

declare(strict_types=1);

namespace Tests\Feature\Friend;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FriendshipTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_request_creates_a_pending_row_and_a_notification(): void
    {
        [$ava, $marcus] = User::factory()->withProfile()->count(2)->create()->all();

        $this->actingAs($ava)
            ->postJson("/api/friends/request/{$marcus->id}", ['message' => 'Long time no see.'])
            ->assertCreated()
            ->assertJsonPath('status', 'pending');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $marcus->id,
            'type' => 'friend_request_received',
        ]);
    }

    #[Test]
    public function accepting_a_request_creates_a_mutual_friendship(): void
    {
        [$ava, $marcus] = User::factory()->withProfile()->count(2)->create()->all();

        $this->actingAs($ava)->postJson("/api/friends/request/{$marcus->id}");

        $request = \App\Models\FriendRequest::query()->first();

        $this->actingAs($marcus)
            ->putJson("/api/friends/request/{$request->id}/accept")
            ->assertOk();

        $this->assertTrue($ava->fresh()->isFriendWith($marcus));
        $this->assertTrue($marcus->fresh()->isFriendWith($ava));
    }

    #[Test]
    public function sending_a_request_to_someone_who_already_sent_one_auto_accepts(): void
    {
        [$ava, $marcus] = User::factory()->withProfile()->count(2)->create()->all();

        $this->actingAs($ava)->postJson("/api/friends/request/{$marcus->id}")->assertCreated();
        $this->actingAs($marcus)->postJson("/api/friends/request/{$ava->id}")->assertCreated();

        $this->assertTrue($ava->fresh()->isFriendWith($marcus));
        $this->assertSame(0, \App\Models\FriendRequest::query()->pending()->count());
    }

    #[Test]
    public function only_the_recipient_can_accept(): void
    {
        [$ava, $marcus] = User::factory()->withProfile()->count(2)->create()->all();

        $this->actingAs($ava)->postJson("/api/friends/request/{$marcus->id}");

        $request = \App\Models\FriendRequest::query()->first();

        $this->actingAs($ava)
            ->putJson("/api/friends/request/{$request->id}/accept")
            ->assertForbidden();
    }

    #[Test]
    public function unfriending_removes_both_directions(): void
    {
        [$ava, $marcus] = User::factory()->withProfile()->count(2)->create()->all();

        app(\App\Services\Social\FriendshipService::class)->acceptDirectly($ava, $marcus);

        $this->actingAs($ava)->deleteJson("/api/friends/{$marcus->id}")->assertOk();

        $this->assertFalse($ava->fresh()->isFriendWith($marcus));
        $this->assertFalse($marcus->fresh()->isFriendWith($ava));
    }

    #[Test]
    public function suggestions_rank_people_with_more_mutual_friends_higher(): void
    {
        $viewer = User::factory()->withProfile()->create();
        $friend = User::factory()->withProfile()->create();

        app(\App\Services\Social\FriendshipService::class)->acceptDirectly($viewer, $friend);

        $wellConnected = User::factory()->withProfile()->create();
        $stranger = User::factory()->withProfile()->create();

        app(\App\Services\Social\FriendshipService::class)->acceptDirectly($friend, $wellConnected);

        $suggestions = app(\App\Services\Social\FriendSuggestionService::class)->for($viewer, 10);

        $this->assertTrue(
            $suggestions->firstWhere('suggested_user_id', $wellConnected->id)->score
            >= $suggestions->firstWhere('suggested_user_id', $stranger->id)->score
        );
    }
}
