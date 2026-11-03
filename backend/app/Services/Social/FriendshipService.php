<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Enums\FriendRequestStatus;
use App\Models\FriendRequest;
use App\Models\Friendship;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The social graph: send / accept / decline / cancel requests, unfriend, and
 * maintain the bidirectional rows that make every downstream query a single
 * indexed read.
 */
final class FriendshipService
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    /**
     * Send a request, or auto-accept when the other side already asked.
     */
    public function sendRequest(User $sender, User $recipient, ?string $message = null): FriendRequest
    {
        if ($sender->id === $recipient->id) {
            abort(422, 'You cannot send a friend request to yourself.');
        }

        if ($sender->isFriendWith($recipient)) {
            abort(409, 'You are already friends with this person.');
        }

        if ($this->isBlockedEitherWay($sender, $recipient)) {
            abort(403, 'You cannot interact with this account.');
        }

        return DB::transaction(function () use ($sender, $recipient, $message): FriendRequest {
            // The other person already asked us: accept instead of stacking a
            // second request in the opposite direction.
            $incoming = FriendRequest::query()
                ->pending()
                ->where('sender_id', $recipient->id)
                ->where('recipient_id', $sender->id)
                ->first();

            if ($incoming !== null) {
                $this->accept($incoming);

                return $incoming->fresh();
            }

            /** @var FriendRequest $request */
            $request = FriendRequest::query()->withTrashed()->firstOrNew([
                'sender_id' => $sender->id,
                'recipient_id' => $recipient->id,
            ]);

            if ($request->exists && $request->trashed()) {
                $request->restore();
            }

            $request->forceFill([
                'status' => FriendRequestStatus::Pending->value,
                'message' => $message,
                'responded_at' => null,
            ])->save();

            $this->notifications->notifyFriendRequest($request);

            return $request->fresh(['sender', 'recipient']);
        });
    }

    public function accept(FriendRequest $request): Friendship
    {
        return DB::transaction(function () use ($request): Friendship {
            $request->forceFill([
                'status' => FriendRequestStatus::Accepted->value,
                'responded_at' => now(),
            ])->save();

            $friendship = $this->createBidirectional(
                $request->sender_id,
                $request->recipient_id
            );

            $this->refreshFriendCounts($request->sender_id);
            $this->refreshFriendCounts($request->recipient_id);

            $this->notifications->notifyFriendRequestAccepted($request);

            return $friendship;
        });
    }

    /**
     * Establish a friendship directly, without a request round-trip.
     *
     * Used by seeders and by the "add friend" shortcut that auto-accepts a
     * request the other person already sent (handled inside sendRequest too).
     */
    public function acceptDirectly(User $a, User $b): Friendship
    {
        $created = now();

        $forward = Friendship::query()->firstOrCreate(
            ['user_id' => $a->id, 'friend_id' => $b->id],
            ['created_at' => $created, 'is_following' => true, 'closeness_score' => 0.5]
        );

        Friendship::query()->firstOrCreate(
            ['user_id' => $b->id, 'friend_id' => $a->id],
            ['created_at' => $created, 'is_following' => true, 'closeness_score' => 0.5]
        );

        foreach ([$a, $b] as $user) {
            $user->profile()->increment('friends_count');
        }

        FriendRequest::query()
            ->where(function ($q) use ($a, $b): void {
                $q->where('sender_id', $a->id)->where('recipient_id', $b->id);
            })
            ->orWhere(function ($q) use ($a, $b): void {
                $q->where('sender_id', $b->id)->where('recipient_id', $a->id);
            })
            ->update(['status' => FriendRequestStatus::Accepted, 'responded_at' => $created]);

        return $forward->fresh();
    }

    public function decline(FriendRequest $request): void
    {
        $request->forceFill([
            'status' => FriendRequestStatus::Declined->value,
            'responded_at' => now(),
        ])->save();
    }

    public function cancel(FriendRequest $request): void
    {
        $request->forceFill([
            'status' => FriendRequestStatus::Cancelled->value,
            'responded_at' => now(),
        ])->save();

        $request->delete();
    }

    public function unfriend(User $user, User $friend): void
    {
        DB::transaction(function () use ($user, $friend): void {
            Friendship::query()
                ->where(fn ($q) => $q->where('user_id', $user->id)->where('friend_id', $friend->id))
                ->orWhere(fn ($q) => $q->where('user_id', $friend->id)->where('friend_id', $user->id))
                ->delete();

            FriendRequest::query()
                ->where(fn ($q) => $q->where('sender_id', $user->id)->where('recipient_id', $friend->id))
                ->orWhere(fn ($q) => $q->where('sender_id', $friend->id)->where('recipient_id', $user->id))
                ->delete();

            $this->refreshFriendCounts($user->id);
            $this->refreshFriendCounts($friend->id);

            Cache::forget(sprintf('friends:%s:%s', $user->id, $friend->id));
            Cache::forget(sprintf('friends:%s:%s', $friend->id, $user->id));
        });
    }

    /**
     * Follow / unfollow: unfollowing keeps the friendship but removes the
     * person's posts from the viewer's feed.
     */
    public function setFollowing(User $user, User $friend, bool $following): void
    {
        Friendship::query()
            ->where('user_id', $user->id)
            ->where('friend_id', $friend->id)
            ->update(['is_following' => $following]);
    }

    /**
     * @return Collection<int, FriendRequest>
     */
    public function receivedRequests(User $user, int $limit = 50): Collection
    {
        return $user->receivedFriendRequests()
            ->pending()
            ->with('sender')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, FriendRequest>
     */
    public function sentRequests(User $user, int $limit = 50): Collection
    {
        return $user->sentFriendRequests()
            ->pending()
            ->with('recipient')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function friends(User $user, int $limit = 100): Collection
    {
        return User::query()->whereIn('id', $user->friendIds())->limit($limit)->get();
    }

    private function createBidirectional(string $a, string $b): Friendship
    {
        /** @var Friendship $forward */
        $forward = Friendship::query()->firstOrCreate(
            ['user_id' => $a, 'friend_id' => $b],
            ['became_friends_at' => now()]
        );

        Friendship::query()->firstOrCreate(
            ['user_id' => $b, 'friend_id' => $a],
            ['became_friends_at' => now()]
        );

        return $forward;
    }

    private function refreshFriendCounts(string $userId): void
    {
        $count = Friendship::query()->where('user_id', $userId)->count();

        DB::table('user_profiles')
            ->where('user_id', $userId)
            ->update(['friends_count' => $count]);
    }

    private function isBlockedEitherWay(User $a, User $b): bool
    {
        return DB::table('blocked_users')
            ->where(fn ($q) => $q->where('user_id', $a->id)->where('blocked_id', $b->id))
            ->orWhere(fn ($q) => $q->where('user_id', $b->id)->where('blocked_id', $a->id))
            ->exists();
    }
}
