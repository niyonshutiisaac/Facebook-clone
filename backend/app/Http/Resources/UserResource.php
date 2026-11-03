<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public projection of a user.
 *
 * Never includes email, phone, birth date or any other field governed by a
 * privacy setting — those are exposed through ProfileResource, which resolves
 * each field's audience before returning it.
 *
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ?User $viewer */
        $viewer = $request->user();

        return [
            'id' => $this->id,
            'username' => $this->username,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'name' => $this->name(),
            'initials' => $this->initials(),
            'avatar_url' => $this->avatar_url,
            'cover_url' => $this->cover_url,
            'bio' => $this->bio,
            'is_verified' => (bool) $this->is_verified,
            'is_online' => (bool) $this->is_online,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),

            'profile' => $this->when(
                $this->relationLoaded('profile') && $this->profile !== null,
                fn (): array => [
                    'headline' => $this->profile->headline,
                    'current_city' => $this->profile->current_city,
                    'hometown' => $this->profile->hometown,
                    'relationship_status' => $this->profile->relationship_status,
                    'work' => $this->profile->work,
                    'education' => $this->profile->education,
                    'friends_count' => (int) $this->profile->friends_count,
                    'followers_count' => (int) $this->profile->followers_count,
                    'posts_count' => (int) $this->profile->posts_count,
                    'photos_count' => (int) $this->profile->photos_count,
                ]
            ),

            // Relationship context relative to the viewer. Costs one indexed
            // query per distinct author, and is cached per viewer for a minute.
            'relationship' => $this->when($viewer !== null && $viewer->id !== $this->id, function () use ($viewer): array {
                return [
                    'is_friend' => $viewer->isFriendWith($this->resource),
                    'is_following' => \App\Models\Friendship::query()
                        ->where('user_id', $viewer->id)
                        ->where('friend_id', $this->id)
                        ->value('is_following') ?? false,
                    'mutual_friends_count' => count($viewer->mutualFriendIds($this->resource)),
                    'request_sent' => $viewer->sentFriendRequests()
                        ->where('recipient_id', $this->id)
                        ->where('status', 'pending')
                        ->exists(),
                    'request_received' => $viewer->receivedFriendRequests()
                        ->where('sender_id', $this->id)
                        ->where('status', 'pending')
                        ->exists(),
                ];
            }),

            'is_self' => $viewer !== null && $viewer->id === $this->id,

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
