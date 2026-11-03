<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\FriendRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FriendRequest
 */
final class FriendRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Models\User $viewer */
        $viewer = $request->user();

        $other = $this->sender_id === $viewer->id ? $this->recipient : $this->sender;

        return [
            'id' => $this->id,
            'status' => $this->status,
            'direction' => $this->sender_id === $viewer->id ? 'sent' : 'received',
            'user' => new UserResource($other),
            'mutual_friends_count' => count($viewer->mutualFriendIds($other)),
            'message' => $this->message,
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_human' => $this->created_at?->diffForHumans(),
        ];
    }
}
