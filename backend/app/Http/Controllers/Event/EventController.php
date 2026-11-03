<?php

declare(strict_types=1);

namespace App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Events: create, RSVP, invite and discover nearby upcoming events.
 */
final class EventController extends Controller
{
    public function discover(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', 'integer', 'min:1', 'max:500'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Event::query()->upcoming()->where('privacy', 'public');

        if (isset($validated['latitude'], $validated['longitude'])) {
            $query->nearby(
                (float) $validated['latitude'],
                (float) $validated['longitude'],
                (int) ($validated['radius'] ?? 50)
            );
        }

        return response()->json(['data' => $query->paginate((int) ($validated['per_page'] ?? 20))]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'privacy' => ['nullable', 'string', 'in:public,private'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'timezone' => ['nullable', 'timezone:all'],
            'location_name' => ['nullable', 'string', 'max:200'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_online' => ['nullable', 'boolean'],
            'online_url' => ['nullable', 'url', 'max:500'],
            'cover' => ['nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,webp'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if ($request->hasFile('cover')) {
            $validated['cover_path'] = \Illuminate\Support\Facades\Storage::disk(config('filesystems.default'))
                ->putFile(sprintf('events/%s', $user->id), $request->file('cover'), ['visibility' => 'public']);
        }

        $event = $user->hostedEvents()->create($validated);

        // The host is automatically going.
        $event->attendees()->create(['user_id' => $user->id, 'status' => 'going']);
        $event->increment('going_count');

        return response()->json(['event' => $event->fresh()], 201);
    }

    public function show(Event $event): JsonResponse
    {
        return response()->json([
            'event' => $event,
            'attendees' => [
                'going' => UserResource::collection(
                    User::query()->whereIn('id',
                        $event->attendees()->where('status', 'going')->pluck('user_id'))->limit(20)->get()
                ),
                'interested' => UserResource::collection(
                    User::query()->whereIn('id',
                        $event->attendees()->where('status', 'interested')->pluck('user_id'))->limit(20)->get()
                ),
            ],
        ]);
    }

    public function update(Request $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'location_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'privacy' => ['sometimes', 'string', 'in:public,private'],
        ]);

        $event->forceFill($validated)->save();

        return response()->json(['event' => $event->fresh()]);
    }

    public function destroy(Event $event): JsonResponse
    {
        $this->authorize('delete', $event);

        $event->delete();

        return response()->json(['message' => 'Event deleted.']);
    }

    public function respond(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate(['status' => ['required', 'string', 'in:going,interested,not_going']]);

        /** @var User $user */
        $user = $request->user();

        $existing = $event->attendees()->where('user_id', $user->id)->first();

        foreach (['going', 'interested'] as $status) {
            if ($existing !== null && $existing->status === $status) {
                $event->decrement($status.'_count');
            }
        }

        $event->attendees()->updateOrCreate(
            ['user_id' => $user->id],
            ['status' => $validated['status'], 'responded_at' => now()]
        );

        if (in_array($validated['status'], ['going', 'interested'], true)) {
            $event->increment($validated['status'].'_count');
        }

        return response()->json(['status' => $validated['status']]);
    }

    public function invite(Request $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'max:500'],
            'user_ids.*' => ['uuid', 'exists:users,id'],
        ]);

        /** @var User $user */
        $user = $request->user();

        foreach ($validated['user_ids'] as $userId) {
            $event->invites()->updateOrCreate(
                ['invitee_id' => $userId],
                ['inviter_id' => $user->id, 'status' => 'invited']
            );
        }

        return response()->json(['invited' => count($validated['user_ids'])]);
    }
}
