<?php

declare(strict_types=1);

namespace App\Http\Controllers\Messenger;

use App\Http\Controllers\Controller;
use App\Http\Requests\Message\StoreConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Media\MediaUploadService;
use App\Services\Messaging\MessengerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ConversationController extends Controller
{
    public function __construct(
        private readonly MessengerService $messenger,
        private readonly MediaUploadService $media,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'filter' => ['nullable', 'string', 'in:all,unread,archived'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $inbox = $this->messenger->inbox(
            $user,
            $validated['filter'] ?? 'all',
            (int) ($validated['limit'] ?? 30)
        );

        return response()->json(ConversationResource::collection(
            $inbox->pluck('conversation')
        ));
    }

    public function store(StoreConversationRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validated();

        if ($validated['type'] === 'private') {
            $other = User::query()->findOrFail($validated['user_id']);
            $conversation = $this->messenger->privateConversation($user, $other);
        } else {
            $avatarPath = null;

            if ($request->hasFile('avatar')) {
                $avatarPath = $this->media->storeAvatar($request->file('avatar'), $user->id)['path'];
            }

            $conversation = $this->messenger->createGroup(
                $user,
                $validated['title'],
                $validated['member_ids'],
                $avatarPath
            );
        }

        $message = null;

        if (filled($validated['message'] ?? null)) {
            $message = $this->messenger->sendMessage($user, $conversation, [
                'body' => $validated['message'],
            ]);
        }

        return response()->json([
            'conversation' => new ConversationResource($conversation->fresh(['participants', 'lastMessage.sender'])),
            'message' => $message !== null ? new MessageResource($message) : null,
        ], 201);
    }

    public function show(Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $conversation->load(['participants', 'lastMessage.sender']);

        return response()->json(new ConversationResource($conversation));
    }

    public function destroy(Request $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Leaving a group is not the same as deleting it: the row survives for
        // the other participants, only our pivot is marked.
        $conversation->participantRecords()
            ->where('user_id', $user->id)
            ->update(['is_left' => true]);

        if ($conversation->type === 'private') {
            $stillIn = $conversation->participantRecords()->where('is_left', false)->count();

            if ($stillIn === 0) {
                $conversation->delete();
            }
        }

        return response()->json(['message' => 'Conversation left.']);
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'before' => ['nullable', 'uuid'],
        ]);

        return response()->json(
            MessageResource::collection(
                $this->messenger->messages(
                    $conversation,
                    (int) ($validated['per_page'] ?? 30),
                    $validated['before'] ?? null
                )
            )
        );
    }

    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate(['up_to_id' => ['nullable', 'uuid']]);

        $count = $this->messenger->markRead($user, $conversation, $validated['up_to_id'] ?? null);

        return response()->json(['marked_read' => $count]);
    }

    public function search(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $validated = $request->validate([
            'q' => ['required', 'string', 'max:200'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json(MessageResource::collection(
            $this->messenger->search($conversation, $validated['q'], (int) ($validated['limit'] ?? 30))
        ));
    }

    public function mute(Request $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $conversation->participantRecords()
            ->where('user_id', $user->id)
            ->update(['is_muted' => $request->boolean('muted', true)]);

        return response()->json(['is_muted' => $request->boolean('muted', true)]);
    }

    public function archive(Request $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $conversation->participantRecords()
            ->where('user_id', $user->id)
            ->update(['is_archived' => $request->boolean('archived', true)]);

        return response()->json(['is_archived' => $request->boolean('archived', true)]);
    }

    public function pin(Request $request, Conversation $conversation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $conversation->participantRecords()
            ->where('user_id', $user->id)
            ->update(['is_pinned' => $request->boolean('pinned', true)]);

        return response()->json(['is_pinned' => $request->boolean('pinned', true)]);
    }
}
