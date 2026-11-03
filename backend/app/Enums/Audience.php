<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The five audience levels Facebook offers on every post.
 */
enum Audience: string
{
    case Public = 'public';
    case Friends = 'friends';
    case FriendsExcept = 'friends_except';
    case Specific = 'specific';
    case OnlyMe = 'only_me';

    /**
     * Audiences that require an explicit user id list on `post_audiences`.
     *
     * @return list<self>
     */
    public static function requiresList(): array
    {
        return [self::FriendsExcept, self::Specific];
    }

    public function requiresUserList(): bool
    {
        return in_array($this, self::requiresList(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Friends => 'Friends',
            self::FriendsExcept => 'Friends except',
            self::Specific => 'Specific friends',
            self::OnlyMe => 'Only me',
        };
    }

    /**
     * Rank used when comparing two audiences for visibility: a higher number
     * is more restrictive, so a viewer that satisfies the most restrictive
     * audience also satisfies every looser one.
     */
    public function restrictiveness(): int
    {
        return match ($this) {
            self::Public => 0,
            self::Friends => 1,
            self::FriendsExcept, self::Specific => 2,
            self::OnlyMe => 3,
        };
    }
}
