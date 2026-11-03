<?php

declare(strict_types=1);

namespace App\Http\Controllers\Friend;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\FriendList;
use App\Models\User;
use App\Services\Social\FriendshipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FriendController extends Controller
{
    public function __construct(private readonly FriendshipService $friendships)
    {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'list_id' => ['nullable', 'uuid', 'exists:friend_lists,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $ids = $user->friendIds();

        $query = User::query()->whereIn('id', $ids)->with('profile');

        if (isset($validated['search'])) {
            $query->search($validated['search']);
        }

        if (isset($validated['list_id'])) {
            $list = FriendList::query()->findOrFail($validated['list_id']);

            $query->whereIn('id', $list->members()->pluck('users.id'));
        }

        return response()->json(UserResource::collection($query->paginate((int) ($validated['per_page'] ?? 30))));
    }

    /**
     * Mutual friends with the "see first" / close-friend flags attached.
     */
    public function closeFriends(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $ids = $user->friendships()
            ->where('is_close_friend', true)
            ->pluck('friend_id');

        return response()->json(UserResource::collection(User::query()->whereIn('id', $ids)->get()));
    }

    public function unfriend(Request $request, User $user): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        $this->friendships->unfriend($viewer, $user);

        return response()->json(['message' => 'You are no longer friends.', 'is_friend' => false]);
    }

    public function follow(Request $request, User $user): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        $this->friendships->setFollowing($viewer, $user, $request->boolean('following', true));

        return response()->json(['is_following' => $request->boolean('following', true)]);
    }

    public function setCloseFriend(Request $request, User $user): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        $close = $request->boolean('close_friend', true);

        $viewer->friendships()
            ->where('friend_id', $user->id)
            ->update(['is_close_friend' => $close]);

        if ($close) {
            $viewer->closeFriends()->firstOrCreate(['close_friend_id' => $user->id]);
        } else {
            $viewer->closeFriends()->where('close_friend_id', $user->id)->delete();
        }

        return response()->json(['is_close_friend' => $close]);
    }

    // ----------------------------------------------------------------- lists

    public function lists(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => $user->friendLists()->withCount('members')->get()->map(fn (FriendList $list): array => [
                'id' => $list->id,
                'name' => $list->name,
                'type' => $list->type,
                'is_default' => $list->is_default,
                'members_count' => $list->members_count,
            ]),
        ]);
    }

    public function storeList(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'in:custom,close_friends,acquaintances,restricted,work,school,family'],
            'member_ids' => ['nullable', 'array', 'max:5000'],
            'member_ids.*' => ['uuid', 'exists:users,id'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $list = $user->friendLists()->create([
            'name' => $validated['name'],
            'type' => $validated['type'] ?? 'custom',
        ]);

        if (isset($validated['member_ids'])) {
            $list->members()->sync($validated['member_ids']);
        }

        return response()->json($list->loadCount('members'), 201);
    }

    public function updateList(Request $request, FriendList $list): JsonResponse
    {
        $this->authorize('update', $list);

        $validated = $request->validate(['name' => ['required', 'string', 'max:100']]);

        $list->forceFill($validated)->save();

        return response()->json($list->fresh());
    }

    public function destroyList(FriendList $list): JsonResponse
    {
        $this->authorize('delete', $list);

        $list->delete();

        return response()->json(['message' => 'List deleted.']);
    }

    public function addMembers(Request $request, FriendList $list): JsonResponse
    {
        $this->authorize('update', $list);

        $validated = $request->validate([
            'member_ids' => ['required', 'array', 'max:5000'],
            'member_ids.*' => ['uuid', 'exists:users,id'],
        ]);

        $list->members()->syncWithoutDetaching($validated['member_ids']);

        return response()->json(['members_count' => $list->members()->count()]);
    }
}
