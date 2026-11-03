<?php

declare(strict_types=1);

namespace App\Http\Controllers\Post;

use App\Http\Controllers\Controller;
use App\Http\Requests\Comment\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Post;
use App\Services\Content\CommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CommentController extends Controller
{
    public function __construct(private readonly CommentService $comments)
    {
    }

    public function index(Request $request, Post $post): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json(
            CommentResource::collection(
                $this->comments->thread($post, (int) ($validated['per_page'] ?? 20))
            )
        );
    }

    public function store(StoreCommentRequest $request, Post $post): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return response()->json(
            new CommentResource($this->comments->create($user, $post, $request->validated())),
            201
        );
    }

    public function update(Request $request, Comment $comment): JsonResponse
    {
        $this->authorize('update', $comment);

        $validated = $request->validate(['body' => ['required', 'string', 'max:8000']]);

        return response()->json(
            new CommentResource($this->comments->update($comment, $validated['body']))
        );
    }

    public function destroy(Comment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);

        $this->comments->delete($comment);

        return response()->json(['message' => 'Comment deleted.']);
    }

    /**
     * Replies to a comment, oldest first so a thread reads top to bottom.
     */
    public function replies(Request $request, Comment $comment): JsonResponse
    {
        $validated = $request->validate(['limit' => ['nullable', 'integer', 'min:1', 'max:100']]);

        return response()->json(
            CommentResource::collection($this->comments->replies($comment, (int) ($validated['limit'] ?? 50)))
        );
    }
}
