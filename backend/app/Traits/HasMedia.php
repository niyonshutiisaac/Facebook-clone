<?php

declare(strict_types=1);

namespace App\Traits;

use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Shared accessors for models that store an avatar and/or a cover image.
 */
trait HasMedia
{
    /**
     * Absolute URL for the avatar, falling back to a generated initials avatar
     * so the UI never has to branch on null.
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(fn (): string => MediaUrl::image($this->avatar_path, 'avatar', (string) $this->getKey()));
    }

    protected function coverUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->cover_path === null
            ? null
            : MediaUrl::image($this->cover_path, 'cover', (string) $this->getKey()));
    }
}
