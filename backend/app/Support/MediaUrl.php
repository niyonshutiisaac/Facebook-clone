<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Single place that turns a stored media path into a URL.
 *
 * Keeps the disk switch (local → S3/MinIO) out of the models and guarantees a
 * deterministic fallback whenever a file is missing or still processing.
 */
final class MediaUrl
{
    /**
     * Public URL for an uploaded image, or a deterministic placeholder when the
     * path is empty. The placeholder is generated from the owner id so a given
     * user always gets the same colour, which keeps the UI stable while media
     * is being processed by the queue.
     */
    public static function image(?string $path, string $kind = 'image', string $seed = ''): string
    {
        if ($path === null || $path === '') {
            return self::placeholder($kind, $seed);
        }

        if (Str::startsWith($path, ['http://', 'https://', 'data:', 'blob:'])) {
            return $path;
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk(config('filesystems.default'));

        return $disk->url($path);
    }

    /**
     * Initials avatar used when a user has no profile picture — mirrors the
     * frontend AvatarFallback component (same palette, same algorithm).
     */
    public static function placeholder(string $kind, string $seed): string
    {
        $palette = ['#1877F2', '#E41E3F', '#31A24C', '#F7B125', '#8B5CF6', '#0084FF', '#E9710F', '#14B8A6'];
        $index = abs(crc32($seed.$kind)) % count($palette);

        $initial = Str::upper(Str::substr($seed !== '' ? $seed : 'u', 0, 1));

        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128" width="128" height="128">
            <rect width="128" height="128" fill="{$palette[$index]}"/>
            <text x="64" y="64" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif"
                  font-size="56" font-weight="600" fill="#FFFFFF"
                  text-anchor="middle" dominant-baseline="central">{$initial}</text>
        </svg>
        SVG;

        return 'data:image/svg+xml;base64,'.base64_encode(trim($svg));
    }
}
