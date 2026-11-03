<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast on the conversation's private channel.
 *
 * `ShouldBroadcastNow` (rather than `ShouldBroadcast`) is deliberate: chat is
 * latency-sensitive and the queue adds a hop for every message. The event is
 * tiny (an id plus an eagerly loaded payload), so the synchronous cost is
 * negligible next to the perceived responsiveness.
 */
final class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public readonly Message $message)
    {
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversation.'.$this->message->conversation_id)];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->message->loadMissing(['sender', 'media', 'replyTo.sender']);

        return [
            'id' => $this->message->id,
            'message' => (new \App\Http\Resources\MessageResource($this->message))
                ->toArray(request()),
        ];
    }
}
