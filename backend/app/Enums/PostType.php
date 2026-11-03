<?php

declare(strict_types=1);

namespace App\Enums;

enum PostType: string
{
    case Text = 'text';
    case Photo = 'photo';
    case Video = 'video';
    case Album = 'album';
    case Link = 'link';
    case Shared = 'shared';
    case Poll = 'poll';
    case Gif = 'gif';
    case Feeling = 'feeling';
    case CheckIn = 'check_in';
    case LifeEvent = 'life_event';
    case AvatarUpdate = 'avatar_update';
    case CoverUpdate = 'cover_update';

    /**
     * Type weight from the ranking model: richer media is worth slightly more
     * than plain text, all else being equal.
     */
    public function weight(): float
    {
        return match ($this) {
            self::Video => 1.5,
            self::Album, self::Photo => 1.3,
            self::Link, self::Shared => 1.1,
            default => 1.0,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Photo => 'Photo',
            self::Video => 'Video',
            self::Album => 'Photo album',
            self::Link => 'Link',
            self::Shared => 'Shared post',
            self::Poll => 'Poll',
            self::Gif => 'GIF',
            self::Feeling => 'Feeling',
            self::CheckIn => 'Check-in',
            self::LifeEvent => 'Life event',
            self::AvatarUpdate => 'Profile picture',
            self::CoverUpdate => 'Cover photo',
        };
    }
}
