<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Models\User;
use Illuminate\Support\Facades\Redis;

/**
 * Online presence backed by Redis.
 *
 * Presence is ephemeral (it belongs in Redis, not MySQL) but it must survive a
 * single heartbeat being lost, so each user's key expires after 90 seconds and
 * is refreshed by the client every 30 seconds. When the key lapses the user is
 * genuinely offline — no explicit "goodbye" event is required.
 */
final class PresenceService
{
    private const string SET_KEY = 'presence:online';
    private const string KEY_PREFIX = 'presence:user';
    private const int TTL_SECONDS = 90;

    /**
     * Heartbeat. Returns the seconds until the client should ping again.
     */
    public function touch(User $user, ?string $connectionId = null): int
    {
        $key = $this->key($user->id);

        Redis::connection('presence')->pipeline(function ($pipe) use ($key, $user, $connectionId): void {
            $pipe->setex($key, self::TTL_SECONDS, json_encode([
                'user_id' => $user->id,
                'connection_id' => $connectionId,
                'at' => now()->toIso8601String(),
            ], JSON_THROW_ON_ERROR));

            $pipe->sadd(self::SET_KEY, [$user->id]);
        });

        if (! $user->is_online) {
            $user->forceFill(['is_online' => true])->saveQuietly();
        }

        return 30;
    }

    public function disconnect(User $user): void
    {
        Redis::connection('presence')->pipeline(function ($pipe) use ($user): void {
            $pipe->del([$this->key($user->id)]);
            $pipe->srem(self::SET_KEY, $user->id);
        });

        $user->forceFill([
            'is_online' => false,
            'last_seen_at' => now(),
        ])->saveQuietly();
    }

    public function isOnline(User $user): bool
    {
        return (bool) Redis::connection('presence')->exists($this->key($user->id));
    }

    /**
     * @return list<string>
     */
    public function onlineUserIds(): array
    {
        /** @var list<string> $members */
        $members = Redis::connection('presence')->smembers(self::SET_KEY);

        if ($members === []) {
            return [];
        }

        // The set can hold ids whose TTL already lapsed; prune as we read.
        $keys = array_map(fn (string $id): string => $this->key($id), $members);
        $alive = Redis::connection('presence')->mget($keys);

        $online = [];

        foreach ($members as $index => $id) {
            if (($alive[$index] ?? null) !== null && ($alive[$index] ?? null) !== false) {
                $online[] = $id;
            }
        }

        if (count($online) !== count($members)) {
            foreach (array_diff($members, $online) as $stale) {
                Redis::connection('presence')->srem(self::SET_KEY, $stale);
            }
        }

        return $online;
    }

    public function onlineCount(): int
    {
        return count($this->onlineUserIds());
    }

    /**
     * "Active now" / "Active 5m ago" string, matching Messenger's copy.
     */
    public function statusLabel(User $user): string
    {
        if ($this->isOnline($user)) {
            return 'Active now';
        }

        $lastSeen = $user->last_seen_at;

        if ($lastSeen === null) {
            return 'Offline';
        }

        $minutes = $lastSeen->diffInMinutes(now());

        return match (true) {
            $minutes < 1 => 'Active just now',
            $minutes < 60 => sprintf('Active %dm ago', $minutes),
            $lastSeen->diffInHours(now()) < 24 => sprintf('Active %dh ago', $lastSeen->diffInHours(now())),
            default => sprintf('Active %s', $lastSeen->diffForHumans(['parts' => 1])),
        };
    }

    private function key(string $userId): string
    {
        return sprintf('%s:%s', self::KEY_PREFIX, $userId);
    }
}
