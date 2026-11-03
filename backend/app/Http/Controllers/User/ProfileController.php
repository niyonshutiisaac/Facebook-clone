<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Media\MediaUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProfileController extends Controller
{
    public function __construct(private readonly MediaUploadService $media)
    {
    }

    /**
     * Profile page bootstrap: the user, their privacy-filtered profile, a
     * friends preview and the relationship context for the viewer.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        /** @var ?User $viewer */
        $viewer = $request->user();

        $user->load(['profile']);

        return response()->json([
            'profile' => new ProfileResource($user),
            'friends_preview' => UserResource::collection($user->friends()->take(9)),
            'mutual_friends' => $viewer !== null && $viewer->id !== $user->id
                ? UserResource::collection(
                    User::query()->whereIn('id', $viewer->mutualFriendIds($user))->limit(9)->get()
                )
                : [],
            'can_edit' => $viewer !== null && $viewer->id === $user->id,
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validated();

        $userFields = array_intersect_key($validated, array_flip([
            'first_name', 'last_name', 'bio', 'gender', 'pronoun', 'locale', 'timezone',
        ]));

        if ($userFields !== []) {
            $user->forceFill($userFields)->save();
        }

        $profileFields = array_intersect_key($validated, array_flip([
            'headline', 'about', 'current_city', 'hometown', 'relationship_status',
            'partner_id', 'website', 'favorite_quotes', 'pronunciation',
            'work', 'education', 'places_lived', 'contact_info', 'basic_info',
            'family_members', 'life_events',
        ]));

        if ($profileFields !== []) {
            $user->profile()->updateOrCreate(['user_id' => $user->id], $profileFields);
        }

        $user->logActivity('profile.updated', 'user', $user->id, [
            'fields' => array_keys($validated),
        ]);

        return response()->json(new ProfileResource($user->fresh(['profile', 'settings'])));
    }

    /**
     * Upload (or replace) the avatar. Changing it is a shareable event, so a
     * post of type `avatar_update` is created — exactly like Facebook.
     */
    public function avatar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'avatar' => ['required', 'file', 'max:30720', 'mimes:jpg,jpeg,png,webp,gif'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $stored = $this->media->storeAvatar($validated['avatar'], $user->id);

        $previous = $user->avatar_path;

        $user->forceFill(['avatar_path' => $stored['path']])->save();

        $user->photos()->create([
            'path' => $stored['path'],
            'disk' => config('filesystems.default'),
            'is_profile_picture' => true,
            'audience' => 'public',
            'variants' => $stored['variants'],
        ]);

        $user->profile?->increment('photos_count');

        app(\App\Services\Content\PostService::class)->create($user, [
            'type' => 'avatar_update',
            'audience' => 'friends',
        ]);

        $user->logActivity('profile.avatar_updated', 'user', $user->id, [
            'previous' => $previous,
        ]);

        return response()->json([
            'avatar_url' => $user->fresh()->avatar_url,
            'variants' => $stored['variants'],
        ]);
    }

    public function destroyAvatar(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill(['avatar_path' => null])->save();
        $user->logActivity('profile.avatar_removed');

        return response()->json(['avatar_url' => $user->fresh()->avatar_url]);
    }

    public function cover(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cover' => ['required', 'file', 'max:40960', 'mimes:jpg,jpeg,png,webp'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $stored = $this->media->storeCover($validated['cover'], $user->id);

        $user->forceFill(['cover_path' => $stored['path']])->save();

        $user->photos()->create([
            'path' => $stored['path'],
            'disk' => config('filesystems.default'),
            'is_cover_photo' => true,
            'audience' => 'public',
            'variants' => $stored['variants'],
        ]);

        app(\App\Services\Content\PostService::class)->create($user, [
            'type' => 'cover_update',
            'audience' => 'friends',
        ]);

        $user->logActivity('profile.cover_updated');

        return response()->json([
            'cover_url' => $user->fresh()->cover_url,
            'variants' => $stored['variants'],
        ]);
    }

    public function destroyCover(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill(['cover_path' => null])->save();

        return response()->json(['message' => 'Cover removed.']);
    }

    /**
     * The user's own timeline.
     */
    public function timeline(Request $request, User $user): JsonResponse
    {
        /** @var ?User $viewer */
        $viewer = $request->user();

        $posts = app(\App\Services\Content\PostService::class)->timelineFor($user, $viewer, 15);

        return response()->json(\App\Http\Resources\PostResource::collection($posts));
    }

    public function photos(Request $request, User $user): JsonResponse
    {
        $photos = $user->photos()->latest()->paginate(24);

        return response()->json([
            'data' => $photos->map(fn ($photo): array => [
                'id' => $photo->id,
                'url' => $photo->url,
                'thumbnail_url' => $photo->thumbnail_url,
                'caption' => $photo->caption,
                'is_profile_picture' => $photo->is_profile_picture,
                'is_cover_photo' => $photo->is_cover_photo,
                'created_at' => $photo->created_at?->toIso8601String(),
            ]),
            'meta' => ['total' => $photos->total(), 'has_more' => $photos->hasMorePages()],
        ]);
    }

    public function friends(Request $request, User $user): JsonResponse
    {
        $friends = User::query()
            ->whereIn('id', $user->friendIds())
            ->with('profile')
            ->paginate(30);

        return response()->json([
            'data' => UserResource::collection($friends),
            'meta' => ['total' => $friends->total()],
        ]);
    }
}
