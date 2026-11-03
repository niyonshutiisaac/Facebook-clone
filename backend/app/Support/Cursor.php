<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Opaque, base64 encoded pagination cursors.
 *
 * Offset pagination breaks on a live feed: rows inserted between page requests
 * shift every window, so users see duplicates. Cursors encode the last seen
 * ranking score + created_at + id and are stable no matter what is inserted
 * above them.
 */
final class Cursor
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function encode(array $payload): string
    {
        return rtrim(strtr(base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function decode(?string $cursor): ?array
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        $json = base64_decode(strtr($cursor, '-_', '+/'), true);

        if ($json === false) {
            return null;
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : null;
    }
}
