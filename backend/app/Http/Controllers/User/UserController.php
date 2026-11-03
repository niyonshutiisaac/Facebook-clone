<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Social\PrivacyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = User::query()->active()->with('profile');

        if (isset($validated['search'])) {
            $query->search($validated['search']);
        }

        return response()->json(UserResource::collection($query->paginate((int) ($validated['per_page'] ?? 30))));
    }

    public function show(User $user): JsonResponse
    {
        return response()->json(new UserResource($user->load('profile')));
    }

    /**
     * Mutual friends between the viewer and another user.
     */
    public function mutualFriends(Request $request, User $user): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        $ids = $viewer->mutualFriendIds($user);

        return response()->json([
            'data' => UserResource::collection(User::query()->whereIn('id', $ids)->get()),
            'meta' => ['total' => count($ids)],
        ]);
    }

    // ------------------------------------------------------------ safety ----

    public function block(Request $request, User $user): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        if ($viewer->id === $user->id) {
            return response()->json(['message' => 'You cannot block yourself.'], 422);
        }

        \App\Models\BlockedUser::query()->firstOrCreate([
            'user_id' => $viewer->id,
            'blocked_id' => $user->id,
        ]);

        app(\App\Services\Social\FriendshipService::class)->unfriend($viewer, $user);

        $viewer->logActivity('privacy.user_blocked', 'user', $user->id);

        return response()->json(['message' => 'Blocked.', 'is_blocked' => true]);
    }

    public function unblock(Request $request, User $user): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        \App\Models\BlockedUser::query()
            ->where('user_id', $viewer->id)
            ->where('blocked_id', $user->id)
            ->delete();

        $viewer->logActivity('privacy.user_unblocked', 'user', $user->id);

        return response()->json(['message' => 'Unblocked.', 'is_blocked' => false]);
    }

    public function mute(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        /** @var User $viewer */
        $viewer = $request->user();

        $viewer->mutes()->updateOrCreate(
            ['muted_id' => $user->id],
            [
                'muted_until' => isset($validated['days'])
                    ? now()->addDays((int) $validated['days'])
                    : null,
            ]
        );

        return response()->json(['message' => 'Muted.', 'is_muted' => true]);
    }

    public function unmute(Request $request, User $user): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        $viewer->mutes()->where('muted_id', $user->id)->delete();

        return response()->json(['message' => 'Unmuted.', 'is_muted' => false]);
    }

    /**
     * Restrict: the person stays a friend but only sees public posts.
     */
    public function restrict(Request $request, User $user): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        \App\Models\Friendship::query()
            ->where('user_id', $viewer->id)
            ->where('friend_id', $user->id)
            ->update(['is_restricted' => true]);

        return response()->json(['message' => 'Restricted.']);
    }

    public function unrestrict(Request $request, User $user): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        \App\Models\Friendship::query()
            ->where('user_id', $viewer->id)
            ->where('friend_id', $user->id)
            ->update(['is_restricted' => false]);

        return response()->json(['message' => 'Restriction removed.']);
    }

    /**
     * Settings: theme, language and notification toggles.
     */
    public function settings(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($request->isMethod('put') || $request->isMethod('patch')) {
            $validated = $request->validate([
                'theme' => ['sometimes', 'string', 'in:light,dark,system'],
                'language' => ['sometimes', 'string', 'in:en,ar,fr,es'],
                'sound_enabled' => ['sometimes', 'boolean'],
                'email_digest_enabled' => ['sometimes', 'boolean'],
                'push_enabled' => ['sometimes', 'boolean'],
                'show_active_status' => ['sometimes', 'boolean'],
                'tag_review_enabled' => ['sometimes', 'boolean'],
            ]);

            $user->settings()->updateOrCreate(['user_id' => $user->id], $validated);

            if (isset($validated['language'])) {
                $user->forceFill(['locale' => $validated['language']])->save();
            }
        }

        return response()->json(['settings' => $user->fresh('settings')->settings]);
    }

    /**
     * Per-field privacy matrix.
     */
    public function privacy(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var PrivacyService $privacy */
        $privacy = app(PrivacyService::class);

        if ($request->isMethod('put') || $request->isMethod('patch')) {
            $validated = $request->validate([
                'settings' => ['required', 'array'],
                'settings.*.field' => ['required', 'string', 'max:64'],
                'settings.*.audience' => ['required', 'string', 'in:public,friends,friends_except,specific,only_me'],
                'settings.*.excluded_user_ids' => ['nullable', 'array'],
                'settings.*.allowed_user_ids' => ['nullable', 'array'],
            ]);

            foreach ($validated['settings'] as $row) {
                $user->privacySettings()->updateOrCreate(
                    ['field' => $row['field']],
                    [
                        'audience' => $row['audience'],
                        'excluded_user_ids' => $row['excluded_user_ids'] ?? null,
                        'allowed_user_ids' => $row['allowed_user_ids'] ?? null,
                    ]
                );
            }

            $user->logActivity('privacy.updated');
        }

        return response()->json([
            'settings' => $user->privacySettings()->get(),
            'available_audiences' => array_column(\App\Enums\Audience::cases(), 'value'),
        ]);
    }

    /**
     * Activity log ("Your activity" in settings).
     */
    public function activityLog(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'action' => ['nullable', 'string', 'max:60'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = $user->activityLogs()->latest();

        if (isset($validated['action'])) {
            $query->where('action', $validated['action']);
        }

        $logs = $query->paginate((int) ($validated['per_page'] ?? 30));

        return response()->json([
            'data' => $logs->map(fn ($log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'subject_type' => $log->subject_type,
                'subject_id' => $log->subject_id,
                'meta' => $log->meta,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toIso8601String(),
            ]),
            'meta' => ['total' => $logs->total()],
        ]);
    }
}
