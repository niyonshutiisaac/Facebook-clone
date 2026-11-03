<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ProfileTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_user_can_update_their_own_profile(): void
    {
        $user = User::factory()->withProfile()->create();

        $this->actingAs($user)
            ->putJson('/api/me', [
                'bio' => 'Product designer.',
                'headline' => 'Designing small things that matter.',
                'current_city' => 'Brooklyn, New York',
            ])
            ->assertOk()
            ->assertJsonPath('bio', 'Product designer.')
            ->assertJsonPath('current_city', 'Brooklyn, New York');
    }

    #[Test]
    public function a_stranger_cannot_see_a_private_email(): void
    {
        $owner = User::factory()->withProfile()->create(['email' => 'private@example.com']);
        $stranger = User::factory()->withProfile()->create();

        $response = $this->actingAs($stranger)->getJson("/api/users/{$owner->id}/profile");

        $this->assertNull(data_get($response->json(), 'profile.contact_info'));
        $this->assertNotSame('private@example.com', data_get($response->json(), 'profile.email'));
    }

    #[Test]
    public function the_owner_sees_their_own_contact_details(): void
    {
        $owner = User::factory()->withProfile()->create(['email' => 'mine@example.com']);

        $response = $this->actingAs($owner)->getJson("/api/users/{$owner->id}/profile");

        $this->assertTrue(data_get($response->json(), 'profile.can_edit'));
    }

    #[Test]
    public function uploading_an_avatar_creates_a_profile_picture_post(): void
    {
        Storage::fake('public');

        $user = User::factory()->withProfile()->create();

        $this->actingAs($user)
            ->postJson('/api/me/avatar', ['avatar' => UploadedFile::fake()->image('me.jpg', 800, 800)])
            ->assertOk()
            ->assertJsonStructure(['avatar_url', 'variants']);

        $this->assertDatabaseHas('photos', ['user_id' => $user->id, 'is_profile_picture' => true]);
        $this->assertDatabaseHas('posts', ['user_id' => $user->id, 'type' => 'avatar_update']);
    }

    #[Test]
    public function blocking_someone_removes_the_friendship(): void
    {
        [$ava, $marcus] = User::factory()->withProfile()->count(2)->create()->all();

        app(\App\Services\Social\FriendshipService::class)->acceptDirectly($ava, $marcus);

        $this->actingAs($ava)->postJson("/api/users/{$marcus->id}/block")->assertOk();

        $this->assertFalse($ava->fresh()->isFriendWith($marcus));
        $this->assertDatabaseHas('blocked_users', ['user_id' => $ava->id, 'blocked_id' => $marcus->id]);
    }
}
