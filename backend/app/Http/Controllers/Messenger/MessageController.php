<?php

declare(strict_types=1);

namespace App\Http\Controllers\Messenger;

use App\Http\Controllers\Controller;
use App\Http\Requests\Message\SendMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Messaging\MessengerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MessageController extends Controller
{
    public function __construct(private readonly MessengerService $messenger)
    {
    }

    public function store(SendMessageRequest $request, Conversation $conversation): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $attributes = $request->validated();

        if ($request->hasFile('upload')) {
            $attributes['upload'] = $request->file('upload');
        }

        $message = $this->messenger->sendMessage($user, $conversation, $attributes);

        return response()->json(new MessageResource($message), 201);
    }

    /**
     * Typing indicator. Throttled client side to one event every three seconds;
     * the server only stores a self-expiring flag.
     */
    public function typing(Request $request, Conversation $conversation): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $this->messenger->setTyping($user, $conversation, $request->boolean('typing', true));

        return response()->json(['typing' => $request->boolean('typing', true)]);
    }

    public function destroy(Request $request, Message $message): JsonResponse
    {
        $this->authorize('delete', $message);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $this->messenger->deleteMessage(
            $user,
            $message,
            $request->input('scope', 'me') === 'everyone' ? 'everyone' : 'me'
        );

        return response()->json(['message' => 'Message removed.']);
    }

    public function react(Request $request, Message $message): JsonResponse
    {
        $validated = $request->validate([
            'reaction' => ['required', 'string', 'in:like,love,care,haha,wow,sad,angry'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $message->reactionRecords()->updateOrCreate(
            ['user_id' => $user->id],
            ['reaction' => $validated['reaction']]
        );

        broadcast(new \App\Events\MessageReacted($message->fresh(), $user))->toOthers();

        return response()->json(new MessageResource($message->fresh(['reactionRecords.user'])));
    }

    public function unreact(Request $request, Message $message): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $message->reactionRecords()->where('user_id', $user->id)->delete();

        return response()->json(['message' => 'Reaction removed.']);
    }

    /**
     * Forward an existing message to one or more conversations.
     */
    public function forward(Request $request, Message $message): JsonResponse
    {
        $validated = $request->validate([
            'conversation_ids' => ['required', 'array', 'max:20'],
            'conversation_ids.*' => ['uuid', 'exists:conversations,id'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $forwarded = [];

        foreach ($validated['conversation_ids'] as $conversationId) {
            $conversation = Conversation::query()->findOrFail($conversationId);

            $forwarded[] = $this->messenger->sendMessage($user, $conversation, [
                'body' => $message->body,
                'type' => $message->type,
                'forwarded_from_id' => $message->id,
            ]);
        }

        return response()->json(MessageResource::collection($forwarded), 201);
    }
}
