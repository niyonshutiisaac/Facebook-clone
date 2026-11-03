<?php

declare(strict_types=1);

namespace App\Services\Media;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

/**
 * Image renditions.
 *
 * The feed never ships an original upload: Facebook-style delivery means a
 * small, medium and large rendition per image, so a 4 MB photo taken on a phone
 * costs a few KB in the scrolling list and is only pulled full-size in the
 * lightbox.
 */
final class ImageProcessingService
{
    /** Rendition definitions: name => max width in pixels. */
    private const array RENDITIONS = [
        'sm' => 320,
        'md' => 720,
        'lg' => 1440,
    ];

    private const array AVATAR_SIZES = [
        'thumb' => 40,
        'small' => 80,
        'medium' => 168,
        'large' => 320,
    ];

    private const array COVER_SIZES = [
        'small' => 640,
        'medium' => 960,
        'large' => 1920,
    ];

    private ?ImageManager $manager = null;

    /**
     * @return array<string, string>
     */
    public function variants(string $path): array
    {
        return $this->generate($path, self::RENDITIONS, 'resize');
    }

    /**
     * @return array<string, string>
     */
    public function avatarVariants(string $path): array
    {
        return $this->generate($path, self::AVATAR_SIZES, 'square');
    }

    /**
     * @return array<string, string>
     */
    public function coverVariants(string $path): array
    {
        return $this->generate($path, self::COVER_SIZES, 'cover');
    }

    /**
     * @param  array<string, int>  $sizes
     * @return array<string, string>
     */
    private function generate(string $path, array $sizes, string $mode): array
    {
        $variants = [];

        try {
            $disk = Storage::disk(config('filesystems.default'));
            $source = $disk->get($path);
            $manager = $this->manager();

            $base = pathinfo($path, PATHINFO_DIRNAME);
            $filename = pathinfo($path, PATHINFO_FILENAME);
            $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';

            foreach ($sizes as $name => $width) {
                $image = $manager->read($source);

                $image = match ($mode) {
                    'square' => $image->cover($width, $width),
                    'cover' => $image->scaleDown(width: $width),
                    default => $image->scaleDown(width: $width),
                };

                $encoded = $image->toJpeg(quality: 82);
                $target = sprintf('%s/%s-%s.jpg', $base, $filename, $name);

                $disk->put($target, (string) $encoded, ['visibility' => 'public']);

                $variants[$name] = $target;
            }
        } catch (\Throwable $e) {
            // Renditions are an optimisation: a failure here must not lose the
            // upload, so we log it and fall back to the original path.
            Log::warning('Image rendition generation failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        return $variants;
    }

    private function manager(): ImageManager
    {
        if ($this->manager === null) {
            $driver = extension_loaded('imagick') ? 'imagick' : 'gd';

            $this->manager = ImageManager::withDriver($driver);
        }

        return $this->manager;
    }
}
