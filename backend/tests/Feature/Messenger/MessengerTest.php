<?php

declare(strict_types=1);

namespace Tests\Feature\Messenger;

use App\Models\User;
use App\Services\Messaging\MessengerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MessengerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_private_conversation_is_reused_between_two_people(): void
    {
        [$ava, $marcus] = User::factory()->withProfile()->count(2)->create()->all();

        /** @var MessengerService $messenger */
        $messenger = app(MessengerService::class);

        $first = $messenger->privateConversation($ava, $marcus);
        $second = $messenger->privateConversation($marcus, $ava);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('conversations', 1);
    }

    #[Test]
    public function sending_a_message_broadcasts_and_updates_last_message(): void
    {
        Event::fake([\App\Events\MessageSent::class]);

        [$ava, $marcus] = User::factory()->withProfile()->count(2)->create()->all();

        /** @var MessengerService $messenger */
        $messenger = app(MessengerService::class);

        $conversation = $messenger->privateConversation($ava, $marcus);
        $message = $messenger->sendMessage($ava, $conversation, ['body' => 'Are you coming on Saturday?']);

        Event::assertDispatched(\App\Events\MessageSent::class);

        $this->assertSame($message->id, $conversation->fresh()->last_message_id);
        $this->assertDatabaseHas('messages', ['body' => 'Are you coming on Saturday?']);
    }

    #[Test]
    public function read_receipts_are_tracked_per_participant(): void
    {
        [$ava, $marcus] = User::factory()->withProfile()->count(2)->create()->all();

        /** @var MessengerService $messenger */
        $messenger = app(MessengerService::class);

        $conversation = $messenger->privateConversation($ava, $marcus);
        $message = $messenger->sendMessage($ava, $conversation, ['body' => 'hello']);

        $this->assertEquals('sent', $message->fresh()->delivery_status->value);

        $messenger->markRead($marcus, $conversation, $message->id);

        $this->assertEquals('read', $message->fresh()->delivery_status->value);
        $this->assertDatabaseCount('message_reads', 1);
    }

    #[Test]
    public function delete_for_me_keeps_the_message_for_the_other_person(): void
    {
        [$ava, $marcus] = User::factory()->withProfile()->count(2)->create()->all();

        /** @var MessengerService $messenger */
        $messenger = app(MessengerService::class);

        $conversation = $messenger->privateConversation($ava, $marcus);
        $message = $messenger->sendMessage($ava, $conversation, ['body' => 'oops']);

        $messenger->deleteMessage($ava, $message, 'me');

        $this->assertTrue($message->fresh()->isDeletedFor($ava));
        $this->assertFalse($message->fresh()->isDeletedFor($marcus));
    }

    #[Test]
    public function delete_for_everyone_hides_the_body_from_both(): void
    {
        [$ava, $marcus] = User::factory()->withProfile()->count(2)->create()->all();

        /** @var MessengerService $messenger */
        $messenger = app(MessengerService::class);

        $conversation = $messenger->privateConversation($ava, $marcus);
        $message = $messenger->sendMessage($ava, $conversation, ['body' => 'oops']);

        $messenger->deleteMessage($ava, $message, 'everyone');

        $this->assertNull($message->fresh()->body);
        $this->assertNotNull($message->fresh()->deleted_for_everyone_at);
    }

    #[Test]
    public function a_message_requires_a_body_an_upload_or_a_gif(): void
    {
        [$ava, $marcus] = User::factory()->withProfile()->count(2)->create()->all();

        /** @var MessengerService $messenger */
        $messenger = app(MessengerService::class);

        $conversation = $messenger->privateConversation($ava, $marcus);

        $this->actingAs($ava)
            ->postJson("/api/conversations/{$conversation->id}/messages", [])
            ->assertUnprocessable();
    }

    #[Test]
    public function only_participants_can_read_a_conversation(): void
    {
        [$ava, $marcus, $stranger] = User::factory()->withProfile()->count(3)->create()->all();

        /** @var MessengerService $messenger */
        $messenger = app(MessengerService::class);

        $conversation = $messenger->privateConversation($ava, $marcus);

        $this->actingAs($stranger)
            ->getJson("/api/conversations/{$conversation->id}/messages")
            ->assertForbidden();
    }
}
