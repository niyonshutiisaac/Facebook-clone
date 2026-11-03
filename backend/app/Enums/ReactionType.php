<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Facebook's six reactions, in the order they appear in the picker.
 */
enum ReactionType: string
{
    case Like = 'like';
    case Love = 'love';
    case Care = 'care';
    case Haha = 'haha';
    case Wow = 'wow';
    case Sad = 'sad';
    case Angry = 'angry';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function emoji(): string
    {
        return match ($this) {
            self::Like => '👍',
            self::Love => '❤️',
            self::Care => '🥰',
            self::Haha => '😆',
            self::Wow => '😮',
            self::Sad => '😢',
            self::Angry => '😡',
        };
    }

    /**
     * Hex colour used by the reaction bar, the picker and the details modal.
     */
    public function color(): string
    {
        return match ($this) {
            self::Like => '#1877F2',
            self::Love => '#F33E58',
            self::Care => '#F33E58',
            self::Haha, self::Wow, self::Sad => '#F7B125',
            self::Angry => '#E9710F',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
