<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\ConversationType;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageType;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Services\Media\MediaUploadService;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

/**
 * Messenger: conversations, messages, read receipts and typing state.
 *
 * Two invariants matter more than anything else here:
 *
 *  1. A private conversation between two people is unique. `privateKey`
 *     (sorted pair of ids) is stored on the row and enforced by a unique index,
 *     so racing "new message" requests cannot create duplicate threads.
 *
 *  2. Message order is monotonic. Pagination is keyset based on
 *     (conversation_id, created_at, id) — never offsets — because a message
 *     arriving mid-scroll must not shift the window.
 */
final class MessengerService
{
    public function __construct(
        private readonly MediaUploadService $media,
        private readonly NotificationService $notifications,
        private readonly PresenceService $presence,
    ) {
    }

    // ---------------------------------------------------------- conversations

    /**
     * Get — or create — the 1:1 conversation between two users.
     */
    public function privateConversation(User $a, User $b): Conversation
    {
        if ($a->id === $b->id) {
            abort(422, 'You cannot start a conversation with yourself.');
        }

        return DB::transaction(function () use ($a, $b): Conversation {
            /** @var Conversation|null $existing */
            $existing = Conversation::query()->between($a, $b)->first();

            if ($existing !== null) {
                // Re-adding someone who previously left the thread.
                $existing->participantRecords()
                    ->whereIn('user_id', [$a->id, $b->id])
                    ->update(['is_left' => false]);

                return $existing;
            }

            /** @var Conversation $conversation */
            $conversation = Conversation::create([
                'type' => ConversationType::Private->value,
                'pair_key' => Conversation::pairKey($a, $b),
                'creator_id' => $a->id,
                'theme_color' => '#0084FF',
            ]);

            foreach ([$a, $b] as $user) {
                $conversation->participantRecords()->create([
                    'user_id' => $user->id,
                    'role' => $user->id === $a->id ? 'admin' : 'member',
                    'joined_at' => now(),
                ]);
            }

            return $conversation->fresh(['participants']);
        });
    }

    /**
     * @param  list<string>  $memberIds
     */
    public function createGroup(User $creator, string $title, array $memberIds, ?string $avatarPath = null): Conversation
    {
        $memberIds = array_values(array_unique([...$memberIds, $creator->id]));

        if (count($memberIds) < 2) {
            abort(422, 'A group conversation needs at least two members.');
        }

        return DB::transaction(function () use ($creator, $title, $memberIds, $avatarPath): Conversation {
            /** @var Conversation $conversation */
            $conversation = Conversation::create([
                'type' => ConversationType::Group->value,
                'title' => $title,
                'creator_id' => $creator->id,
                'avatar_path' => $avatarPath,
                'theme_color' => '#0084FF',
            ]);

            foreach ($memberIds as $memberId) {
                $conversation->participantRecords()->create([
                    'user_id' => $memberId,
                    'role' => $memberId === $creator->id ? 'admin' : 'member',
                    'joined_at' => now(),
                ]);
            }

            return $conversation->fresh(['participants']);
        });
    }

    /**
     * The inbox: every conversation the user is part of, most recent first,
     * with unread counts resolved in the same query round.
     *
     * @return SupportCollection<int, array{conversation: Conversation, unread: int}>
     */
    public function inbox(User $user, ?string $filter = 'all', int $limit = 30): SupportCollection
    {
        $query = Conversation::query()
            ->with(['participants', 'lastMessage.sender'])
            ->whereHas('participantRecords', function ($q) use ($user, $filter): void {
                $q->where('user_id', $user->id)->where('is_left', false);

                if ($filter === 'unread') {
                    $q->where(function ($sub): void {
                        $sub->whereNull('last_read_at');
                    });
                }

                if ($filter === 'archived') {
                    $q->where('is_archived', true);
                } elseif ($filter !== 'all') {
                    $q->where('is_archived', false);
                }
            })
            ->orderByDesc('last_message_at');

        return $query->limit($limit)
            ->get()
            ->map(fn (Conversation $c): array => [
                'conversation' => $c,
                'unread' => $c->unreadCountFor($user),
            ])
            ->when($filter === 'unread', fn (SupportCollection $c) => $c->where('unread', '>', 0))
            ->values();
    }

    // --------------------------------------------------------------- messages

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function sendMessage(User $sender, Conversation $conversation, array $attributes): Message
    {
        $this->assertParticipant($sender, $conversation);

        $body = (string) ($attributes['body'] ?? '');

        if ($body === '' && ! isset($attributes['upload']) && ! isset($attributes['gif_url'])) {
            abort(422, 'A message must have a body or an attachment.');
        }

        return DB::transaction(function () use ($sender, $conversation, $attributes, $body): Message {
            /** @var Message $message */
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'reply_to_id' => $attributes['reply_to_id'] ?? null,
                'forwarded_from_id' => $attributes['forwarded_from_id'] ?? null,
                'type' => $attributes['type'] ?? MessageType::Text->value,
                'body' => $body !== '' ? $body : null,
                'delivery_status' => MessageDeliveryStatus::Sent->value,
            ]);

            if (isset($attributes['upload'])) {
                $stored = $this->media->storeMessageMedia($attributes['upload'], $message);
                $message->media()->create($stored);

                $message->forceFill(['type' => $stored['type']])->save();
            }

            $conversation->forceFill([
                'last_message_id' => $message->id,
                'last_message_at' => $message->created_at,
            ])->save();

            // Stop typing the moment a message is sent.
            $conversation->participantRecords()
                ->where('user_id', $sender->id)
                ->update(['is_typing' => false, 'typing_at' => null]);

            $message->load(['sender', 'media', 'replyTo.sender']);

            broadcast(new MessageSent($message))->toOthers();

            $this->notifications->notifyNewMessage($message);

            return $message;
        });
    }

    /**
     * Keyset pagination, newest page first.
     *
     * @return LengthAwarePaginator<Message>
     */
    public function messages(Conversation $conversation, int $perPage = 30, ?string $beforeId = null): LengthAwarePaginator
    {
        $query = $conversation->messages()
            ->with(['sender', 'media', 'replyTo.sender', 'reactionRecords.user'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($beforeId !== null) {
            $anchor = Message::query()->find($beforeId);

            if ($anchor !== null) {
                $query->where(function ($q) use ($anchor): void {
                    $q->where('created_at', '<', $anchor->created_at)
                        ->orWhere(fn ($b) => $b->where('created_at', '=', $anchor->created_at)
                            ->where('id', '<', $anchor->id));
                });
            }
        }

        /** @var LengthAwarePaginator<Message> $page */
        $page = $query->paginate($perPage);

        // Chat renders oldest → newest, so flip the slice.
        $page->setCollection($page->getCollection()->reverse()->values());

        return $page;
    }

    /**
     * Mark everything up to (and including) `$upToId` as read.
     */
    public function markRead(User $reader, Conversation $conversation, ?string $upToId = null): int
    {
        $pivot = $conversation->participantRecords()->where('user_id', $reader->id)->firstOrFail();

        $query = $conversation->messages()
            ->where('sender_id', '!=', $reader->id)
            ->where(function ($q) use ($pivot): void {
                if ($pivot->last_read_at !== null) {
                    $q->where('created_at', '>', $pivot->last_read_at);
                }
            });

        if ($upToId !== null) {
            $query->where('id', '<=', $upToId);
        }

        /** @var Collection<int, Message> $unread */
        $unread = $query->get();

        foreach ($unread as $message) {
            $message->markRead($reader);
        }

        $last = $unread->last();

        $pivot->forceFill([
            'last_read_at' => now(),
            'last_read_message_id' => $last?->id ?? $pivot->last_read_message_id,
        ])->save();

        foreach ($unread as $message) {
            broadcast(new \App\Events\MessageRead($message, $reader))->toOthers();
        }

        return $unread->count();
    }

    /**
     * Delete for me (hide from the deleter) or for everyone (tombstone the row
     * but keep the thread intact).
     */
    public function deleteMessage(User $user, Message $message, string $scope = 'me'): void
    {
        if ($scope === 'everyone' && $message->sender_id !== $user->id) {
            abort(403, 'Only the sender can remove a message for everyone.');
        }

        DB::transaction(function () use ($user, $message, $scope): void {
            $message->deletions()->firstOrCreate(
                ['user_id' => $user->id, 'scope' => $scope],
                ['created_at' => now()]
            );

            if ($scope === 'everyone') {
                $message->forceFill([
                    'body' => null,
                    'deleted_for_everyone_at' => now(),
                ])->save();

                $message->media()->delete();

                broadcast(new \App\Events\MessageDeleted($message))->toOthers();
            }
        });
    }

    /**
     * Typing indicator. Self-expiring: the `is_typing` flag is only honoured
     * for ten seconds (see ConversationParticipant::scopeTyping), so a dropped
     * "stop typing" event cannot leave a ghost indicator.
     */
    public function setTyping(User $user, Conversation $conversation, bool $typing): void
    {
        $conversation->participantRecords()
            ->where('user_id', $user->id)
            ->update([
                'is_typing' => $typing,
                'typing_at' => $typing ? now() : null,
            ]);

        broadcast($typing
            ? new \App\Events\UserStartedTyping($user, $conversation)
            : new \App\Events\UserStoppedTyping($user, $conversation)
        )->toOthers();
    }

    /**
     * @return Collection<int, Message>
     */
    public function search(Conversation $conversation, string $term, int $limit = 30): Collection
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $conversation->messages()
            ->with('sender')
            ->where('body', 'like', $like)
            ->latest()
            ->limit($limit)
            ->get();
    }

    // ---------------------------------------------------------------- helpers

    public function assertParticipant(User $user, Conversation $conversation): ConversationParticipant
    {
        $pivot = $conversation->participantRecords()
            ->where('user_id', $user->id)
            ->where('is_left', false)
            ->first();

        if ($pivot === null) {
            abort(403, 'You are not a participant in this conversation.');
        }

        return $pivot;
    }

    /**
     * Online friends for the right-hand contacts rail.
     *
     * @return Collection<int, User>
     */
    public function onlineFriends(User $user): Collection
    {
        $friendIds = $user->friendIds();
        $onlineIds = $this->presence->onlineUserIds();

        return User::query()
            ->whereIn('id', array_values(array_intersect($friendIds, $onlineIds)))
            ->get();
    }
}
