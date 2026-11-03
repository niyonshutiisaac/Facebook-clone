<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Models\Message;
use App\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Validates and stores user uploads.
 *
 * Security posture:
 *  - MIME type is resolved from file *content* (finfo), never from the client
 *    supplied header, and must be on the allow-list.
 *  - Filenames are regenerated; client names are kept only as metadata, so a
 *    crafted name can never traverse the filesystem.
 *  - Storage paths are sharded by date to keep directory sizes sane.
 */
final class MediaUploadService
{
    /**
     * @var array<string, list<string>>
     */
    private const array ALLOWED = [
        'image' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'],
        'video' => ['video/mp4', 'video/quicktime', 'video/webm', 'video/x-matroska'],
        'audio' => ['audio/mpeg', 'audio/mp4', 'audio/wav', 'audio/webm'],
        'file' => ['application/pdf', 'text/plain', 'application/zip', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ];

    /** @var array<string, int> maximum bytes per kind */
    private const array MAX_BYTES = [
        'image' => 30 * 1024 * 1024,
        'video' => 512 * 1024 * 1024,
        'audio' => 50 * 1024 * 1024,
        'file' => 100 * 1024 * 1024,
    ];

    public function __construct(private readonly ImageProcessingService $images)
    {
    }

    /**
     * @return array{path: string, type: string, disk: string, mime_type: string, size: int, original_name: string, width: ?int, height: ?int, duration: ?int, variants: array<string, string>}
     */
    public function storePostMedia(UploadedFile $file, Post $post): array
    {
        $kind = $this->classify($file);
        $path = $this->store($file, sprintf('posts/%s', $post->user_id));

        $metadata = [
            'path' => $path,
            'type' => $kind === 'video' ? 'video' : 'image',
            'disk' => config('filesystems.default'),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'original_name' => $file->getClientOriginalName(),
            'width' => null,
            'height' => null,
            'duration' => null,
            'variants' => [],
        ];

        if ($kind === 'image') {
            $dimensions = @getimagesize($file->getRealPath());

            if ($dimensions !== false) {
                $metadata['width'] = $dimensions[0];
                $metadata['height'] = $dimensions[1];
            }

            $metadata['variants'] = $this->images->variants($path);
            $metadata['thumbnail_path'] = $metadata['variants']['md'] ?? $path;
        }

        return $metadata;
    }

    /**
     * @return array{path: string, type: string, disk: string, mime_type: string, size: int, original_name: string, width: ?int, height: ?int, duration: ?int}
     */
    public function storeMessageMedia(UploadedFile $file, Message $message): array
    {
        $kind = $this->classify($file);
        $path = $this->store($file, sprintf('messages/%s', $message->conversation_id));

        return [
            'path' => $path,
            'type' => match ($kind) {
                'video' => 'video',
                'audio' => 'audio',
                'file' => 'file',
                default => 'image',
            },
            'disk' => config('filesystems.default'),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'original_name' => $file->getClientOriginalName(),
            'width' => null,
            'height' => null,
            'duration' => null,
        ];
    }

    /**
     * Avatar and cover uploads: cropped by the client before upload, so we only
     * generate the renditions and never trust the dimensions.
     *
     * @return array{path: string, variants: array<string, string>}
     */
    public function storeAvatar(UploadedFile $file, string $userId): array
    {
        if ($this->classify($file) !== 'image') {
            abort(422, 'Profile pictures must be images.');
        }

        $path = $this->store($file, sprintf('avatars/%s', $userId));

        return [
            'path' => $path,
            'variants' => $this->images->avatarVariants($path),
        ];
    }

    /**
     * @return array{path: string, variants: array<string, string>}
     */
    public function storeCover(UploadedFile $file, string $userId): array
    {
        if ($this->classify($file) !== 'image') {
            abort(422, 'Cover photos must be images.');
        }

        $path = $this->store($file, sprintf('covers/%s', $userId));

        return [
            'path' => $path,
            'variants' => $this->images->coverVariants($path),
        ];
    }

    public function looksLikeVideo(mixed $file): bool
    {
        return $file instanceof UploadedFile && $this->classify($file) === 'video';
    }

    /**
     * Move the upload to permanent storage under a generated, sharded name.
     */
    private function store(UploadedFile $file, string $directory): string
    {
        $this->validate($file);

        $extension = $file->guessExtension() ?? 'bin';

        $path = sprintf(
            '%s/%s/%s.%s',
            $directory,
            now()->format('Y/m'),
            Str::lower(Str::uuid()->toString()),
            $extension
        );

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk(config('filesystems.default'));
        $disk->put($path, $file->getRealPath() !== false
            ? fopen($file->getRealPath(), 'rb')
            : $file->getContent(), ['visibility' => 'public']);

        return $path;
    }

    private function validate(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            abort(422, 'The upload failed. Please try again.');
        }

        $kind = $this->classify($file);

        if ($kind === 'unknown') {
            abort(415, sprintf('Files of type "%s" are not allowed.', $file->getMimeType()));
        }

        if ($file->getSize() > self::MAX_BYTES[$kind]) {
            abort(413, sprintf(
                'The file is larger than the %d MB limit for %s uploads.',
                (int) (self::MAX_BYTES[$kind] / 1024 / 1024),
                $kind
            ));
        }
    }

    /**
     * Resolve the file kind from real content, not from the client header.
     *
     * @return 'image'|'video'|'audio'|'file'|'unknown'
     */
    private function classify(UploadedFile $file): string
    {
        $mime = $file->getMimeType();

        foreach (self::ALLOWED as $kind => $mimes) {
            if (in_array($mime, $mimes, true)) {
                return $kind;
            }
        }

        return 'unknown';
    }
}
