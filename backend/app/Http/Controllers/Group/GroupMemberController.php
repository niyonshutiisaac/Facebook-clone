<?php

declare(strict_types=1);

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GroupMemberController extends Controller
{
    public function approve(Request $request, Group $group, User $user): JsonResponse
    {
        $this->authorize('moderate', $group);

        $group->joinRequests()
            ->where('user_id', $user->id)
            ->update(['status' => 'approved', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);

        $group->membershipRecords()->updateOrCreate(
            ['user_id' => $user->id],
            ['status' => 'active', 'role' => 'member', 'joined_at' => now(), 'badge' => 'new_member']
        );

        $group->increment('members_count');
        $group->decrement('pending_members_count');

        return response()->json(['message' => 'Request approved.']);
    }

    public function destroy(Request $request, Group $group, User $user): JsonResponse
    {
        $this->authorize('moderate', $group);

        $group->membershipRecords()->where('user_id', $user->id)->delete();
        $group->decrement('members_count');

        return response()->json(['message' => 'Member removed.']);
    }

    public function ban(Request $request, Group $group, User $user): JsonResponse
    {
        $this->authorize('moderate', $group);

        $group->membershipRecords()->updateOrCreate(
            ['user_id' => $user->id],
            ['status' => 'banned', 'role' => 'member']
        );

        $group->decrement('members_count');

        return response()->json(['message' => 'Member banned.']);
    }

    public function role(Request $request, Group $group, User $user): JsonResponse
    {
        $this->authorize('moderate', $group);

        $validated = $request->validate(['role' => ['required', 'string', 'in:member,moderator,admin']]);

        $group->membershipRecords()->where('user_id', $user->id)->update(['role' => $validated['role']]);

        $group->admins()->updateOrCreate(
            ['user_id' => $user->id],
            ['role' => $validated['role'], 'appointed_at' => now()]
        );

        return response()->json(['role' => $validated['role']]);
    }
}
