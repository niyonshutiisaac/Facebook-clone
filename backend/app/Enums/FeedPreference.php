<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Per-source feed control exposed in the "Manage news feed" screen.
 */
enum FeedPreference: string
{
    case SeeFirst = 'see_first';
    case Default = 'default';
    case Unfollow = 'unfollow';
    case Snooze = 'snooze';
}
