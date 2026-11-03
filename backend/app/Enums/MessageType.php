<?php

declare(strict_types=1);

namespace App\Enums;

enum MessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case File = 'file';
    case Gif = 'gif';
    case Sticker = 'sticker';
    case System = 'system';
    case Location = 'location';
}
