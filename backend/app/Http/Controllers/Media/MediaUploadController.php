<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\PhotoAlbum;
use App\Services\Media\MediaUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MediaUploadController extends Controller
{
    public function __construct(private readonly MediaUploadService $media)
    {
    }

    /**
     * Generic single-file upload (used by the composer's "photo" button and by
     * the story/reel creators).
     */
    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:524288',
                'mimes:jpg,jpeg,png,gif,webp,avif,mp4,mov,webm'],
            'purpose' => ['nullable', 'string', 'in:post,comment,message,story,reel,avatar,cover'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $purpose = $validated['purpose'] ?? 'post';

        $result = match ($purpose) {
            'avatar' => $this->media->storeAvatar($validated['file'], $user->id),
            'cover' => $this->media->storeCover($validated['file'], $user->id),
            default => ['path' => \Illuminate\Support\Facades\Storage::disk(config('filesystems.default'))->putFile(
                sprintf('uploads/%s/%s', $user->id, now()->format('Y/m')),
                $validated['file'],
                ['visibility' => 'public']
            )],
        };

        return response()->json([
            'path' => $result['path'],
            'url' => \App\Support\MediaUrl::image($result['path'], 'image', (string) $user->id),
            'variants' => $result['variants'] ?? [],
        ], 201);
    }

    public function uploadMultiple(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'files' => ['required', 'array', 'max:10'],
            'files.*' => ['file', 'max:524288', 'mimes:jpg,jpeg,png,gif,webp,avif,mp4,mov,webm'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $uploaded = [];

        foreach ($validated['files'] as $file) {
            $path = \Illuminate\Support\Facades\Storage::disk(config('filesystems.default'))->putFile(
                sprintf('uploads/%s/%s', $user->id, now()->format('Y/m')),
                $file,
                ['visibility' => 'public']
            );

            $uploaded[] = [
                'path' => $path,
                'url' => \App\Support\MediaUrl::image($path, 'image', (string) $user->id),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ];
        }

        return response()->json(['data' => $uploaded], 201);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $photo = Photo::query()->where('user_id', $user->id)->findOrFail($id);

        \Illuminate\Support\Facades\Storage::disk($photo->disk)->delete($photo->path);

        $photo->delete();

        return response()->json(['message' => 'Media removed.']);
    }

    // ---------------------------------------------------------------- albums

    public function albums(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return response()->json(['data' => $user->albums()->withCount('photos')->latest()->get()]);
    }

    public function storeAlbum(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'audience' => ['nullable', 'string', 'in:public,friends,only_me'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        return response()->json($user->albums()->create($validated), 201);
    }

    public function showAlbum(PhotoAlbum $album): JsonResponse
    {
        $this->authorize('view', $album);

        return response()->json([
            'album' => $album->loadCount('photos'),
            'photos' => $album->photos()->paginate(30),
        ]);
    }

    public function updateAlbum(Request $request, PhotoAlbum $album): JsonResponse
    {
        $this->authorize('update', $album);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'audience' => ['sometimes', 'string', 'in:public,friends,only_me'],
        ]);

        $album->forceFill($validated)->save();

        return response()->json($album->fresh());
    }

    public function destroyAlbum(PhotoAlbum $album): JsonResponse
    {
        $this->authorize('delete', $album);

        $album->delete();

        return response()->json(['message' => 'Album deleted.']);
    }
}
