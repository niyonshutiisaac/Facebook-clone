<?php

declare(strict_types=1);

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use App\Http\Resources\GroupResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\UserResource;
use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Groups: discovery, membership and the group feed.
 * Moderation (approvals, roles, bans) lives in GroupMemberController.
 */
final class GroupController extends Controller
{
    public function discover(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Group::query()->visible();

        if (isset($validated['search'])) {
            $query->where('name', 'like', '%'.addcslashes($validated['search'], '%_\\').'%');
        }

        return response()->json(GroupResource::collection(
            $query->orderByDesc('members_count')->paginate((int) ($validated['per_page'] ?? 20))
        ));
    }

    public function myGroups(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(GroupResource::collection(
            $user->groups()->wherePivot('status', 'active')->get()
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['nullable', 'string', 'in:general,social_learning,buy_and_sell'],
            'privacy' => ['nullable', 'string', 'in:public,private_visible,private_hidden'],
            'topics' => ['nullable', 'array', 'max:10'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $group = $user->ownedGroups()->create($validated);

        $group->membershipRecords()->create([
            'user_id' => $user->id,
            'role' => 'admin',
            'status' => 'active',
            'joined_at' => now(),
            'badge' => 'founding_member',
        ]);

        $group->increment('members_count');

        return response()->json(new GroupResource($group->fresh()), 201);
    }

    public function show(Group $group): JsonResponse
    {
        return response()->json([
            'group' => new GroupResource($group),
            'rules' => $group->rules()->get(['id', 'title', 'description']),
            'admins' => UserResource::collection(
                User::query()->whereIn('id', $group->admins()->pluck('user_id'))->get()
            ),
        ]);
    }

    public function update(Request $request, Group $group): JsonResponse
    {
        $this->authorize('update', $group);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'privacy' => ['sometimes', 'string', 'in:public,private_visible,private_hidden'],
            'requires_post_approval' => ['sometimes', 'boolean'],
        ]);

        $group->forceFill($validated)->save();

        return response()->json(new GroupResource($group->fresh()));
    }

    public function destroy(Group $group): JsonResponse
    {
        $this->authorize('delete', $group);

        $group->delete();

        return response()->json(['message' => 'Group deleted.']);
    }

    public function posts(Group $group): JsonResponse
    {
        $posts = $group->posts()
            ->with(['author', 'media', 'link.preview'])
            ->whereHas(
                'groupPosts',
                fn ($q) => $q->where('group_posts.group_id', $group->id)->where('status', 'approved'),
            )
            ->latest()
            ->paginate(15);

        return response()->json(PostResource::collection($posts));
    }

    /**
     * The moderation queue: posts awaiting an admin's decision.
     */
    public function pendingPosts(Group $group): JsonResponse
    {
        $this->authorize('moderate', $group);

        $posts = $group->posts()
            ->with(['author', 'media', 'link.preview'])
            ->whereHas(
                'groupPosts',
                fn ($q) => $q->where('group_posts.group_id', $group->id)->where('status', 'pending'),
            )
            ->latest()
            ->paginate(15);

        return response()->json(PostResource::collection($posts));
    }

    public function approvePost(Request $request, Group $group, Post $post): JsonResponse
    {
        $this->authorize('moderate', $group);

        /** @var User $user */
        $user = $request->user();

        $group->groupPosts()->where('post_id', $post->id)->update([
            'status' => 'approved',
            'approved_by' => $user->id,
        ]);

        $group->increment('posts_count');

        return response()->json(['message' => 'Post approved.']);
    }

    public function declinePost(Group $group, Post $post): JsonResponse
    {
        $this->authorize('moderate', $group);

        $group->groupPosts()->where('post_id', $post->id)->update(['status' => 'rejected']);

        return response()->json(['message' => 'Post declined.']);
    }

    public function storePost(Request $request, Group $group): JsonResponse
    {
        $this->authorize('post', $group);

        /** @var User $user */
        $user = $request->user();

        $post = app(\App\Services\Content\PostService::class)->create(
            $user,
            array_merge($request->all(), [
                'group_id' => $group->id,
                'is_pending_approval' => $group->requires_post_approval && ! $group->isAdmin($user),
            ]),
            $request->file('media', [])
        );

        return response()->json(new PostResource($post), 201);
    }

    public function members(Request $request, Group $group): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['nullable', 'string', 'in:member,moderator,admin'],
            'status' => ['nullable', 'string', 'in:active,pending,banned'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = $group->membershipRecords()->with('user');

        foreach (['role', 'status'] as $filter) {
            if (isset($validated[$filter])) {
                $query->where($filter, $validated[$filter]);
            }
        }

        $members = $query->latest('joined_at')->paginate((int) ($validated['per_page'] ?? 30));

        return response()->json([
            'data' => $members->map(fn ($m): array => [
                'id' => $m->id,
                'user' => new UserResource($m->user),
                'role' => $m->role,
                'status' => $m->status,
                'badge' => $m->badge,
                'joined_at' => $m->joined_at?->toIso8601String(),
            ]),
            'meta' => ['total' => $members->total()],
        ]);
    }

    public function join(Request $request, Group $group): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($group->membershipFor($user)?->status === 'active') {
            return response()->json(['message' => 'You are already a member.'], 409);
        }

        if ($group->isPublic()) {
            $group->membershipRecords()->updateOrCreate(
                ['user_id' => $user->id],
                ['status' => 'active', 'role' => 'member', 'joined_at' => now()]
            );

            $group->increment('members_count');

            return response()->json(['status' => 'active'], 201);
        }

        $group->joinRequests()->updateOrCreate(
            ['user_id' => $user->id],
            ['message' => $request->input('message'), 'answers' => $request->input('answers'), 'status' => 'pending']
        );

        $group->increment('pending_members_count');

        return response()->json(['status' => 'pending'], 201);
    }

    public function leave(Request $request, Group $group): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $group->membershipRecords()->where('user_id', $user->id)->delete();
        $group->decrement('members_count');

        return response()->json(['message' => 'You left the group.']);
    }
}
