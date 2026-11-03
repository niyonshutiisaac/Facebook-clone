<?php

declare(strict_types=1);

namespace App\Http\Controllers\Post;

use App\Enums\ReactionType;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\Content\ReactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReactionController extends Controller
{
    public function __construct(private readonly ReactionService $reactions)
    {
    }

    /**
     * Toggle the viewer's reaction on a post or comment.
     *
     * One endpoint for both surfaces because reactions are polymorphic — the
     * client passes the target type and never needs two code paths.
     */
    public function toggle(Request $request, string $targetType, string $targetId): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', ReactionType::values())],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $reaction = $this->reactions->toggle(
            $targetType,
            $targetId,
            $user,
            ReactionType::from($validated['type'])
        );

        return response()->json([
            'reaction' => $reaction?->type,
            'summary' => $this->reactions->summary($targetType, $targetId),
            'total' => array_sum($this->reactions->summary($targetType, $targetId)),
        ]);
    }

    public function summary(string $targetType, string $targetId): JsonResponse
    {
        $summary = $this->reactions->summary($targetType, $targetId);

        return response()->json([
            'summary' => $summary,
            'total' => array_sum($summary),
        ]);
    }

    /**
     * "Who reacted" modal, optionally filtered to one reaction type.
     */
    public function reactors(Request $request, string $targetType, string $targetId): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string', 'in:'.implode(',', ReactionType::values())],
        ]);

        $type = isset($validated['type']) ? ReactionType::from($validated['type']) : null;

        $reactors = $this->reactions->reactors($targetType, $targetId, $type);

        return response()->json([
            'data' => UserResource::collection($reactors->pluck('user')->filter()),
            'meta' => ['total' => $reactors->count()],
        ]);
    }
}
