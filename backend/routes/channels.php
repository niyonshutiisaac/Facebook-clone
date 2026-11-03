<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast channel authorisation
|--------------------------------------------------------------------------
|
| Every channel here is private or presence: real-time traffic must never be
| readable by someone who is not part of the conversation, the group or the
| recipient's own notification stream.
|
*/

Broadcast::channel('user.{userId}', function (User $user, string $userId): bool {
    return $user->id === $userId;
});

Broadcast::channel('feed.{userId}', function (User $user, string $userId): bool {
    return $user->id === $userId;
});

/**
 * Only active participants can subscribe to a conversation's real-time events.
 * `is_left` is checked so someone who left a group chat cannot keep listening.
 */
Broadcast::channel('conversation.{conversationId}', function (User $user, string $conversationId): ?array {
    $conversation = Conversation::query()->find($conversationId);

    if ($conversation === null) {
        return null;
    }

    $participant = $conversation->participantRecords()
        ->where('user_id', $user->id)
        ->where('is_left', false)
        ->first();

    if ($participant === null) {
        return null;
    }

    // Data returned here is exposed to other subscribers on presence channels.
    return [
        'id' => $user->id,
        'name' => $user->name(),
        'avatar_url' => $user->avatar_url,
    ];
});

Broadcast::channel('group.{groupId}', function (User $user, string $groupId): ?array {
    $group = Group::query()->find($groupId);

    if ($group === null) {
        return null;
    }

    // Hidden groups expose membership presence to members only.
    if ($group->isHidden() && ! $group->membershipRecords()
        ->where('user_id', $user->id)
        ->where('status', 'active')
        ->exists()) {
        return null;
    }

    return [
        'id' => $user->id,
        'name' => $user->name(),
        'avatar_url' => $user->avatar_url,
    ];
});

/*
|--------------------------------------------------------------------------
| Presence channels
|--------------------------------------------------------------------------
*/

Broadcast::channel('online', function (User $user): ?array {
    if (! $user->settings?->show_active_status) {
        // Opting out of active status must not break the channel — we return
        // minimal data so the user is simply absent from the roster.
        return null;
    }

    return [
        'id' => $user->id,
        'name' => $user->name(),
        'avatar_url' => $user->avatar_url,
    ];
});
