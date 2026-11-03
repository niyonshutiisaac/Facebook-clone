<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Post;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Notifies each follower's `feed.{userId}` channel that new content is
 * available, which powers the "3 new posts" pill at the top of the feed.
 */
final class PostCreated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  list<string>  $followerIds
     */
    public function __construct(
        public readonly Post $post,
        public readonly array $followerIds = [],
    ) {
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_map(
            static fn (string $id): PrivateChannel => new PrivateChannel('feed.'.$id),
            $this->followerIds
        );
    }

    public function broadcastAs(): string
    {
        return 'post.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'post_id' => $this->post->id,
            'author_id' => $this->post->user_id,
            'author_name' => $this->post->author?->name(),
            'created_at' => $this->post->created_at?->toIso8601String(),
        ];
    }
}
