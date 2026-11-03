<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Enums\Audience;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Answers "can this viewer see this thing?" for posts, profile fields, photos
 * and stories.
 *
 * Rules, in order:
 *   1. A block in either direction wins — no content is visible, ever.
 *   2. `only_me` is visible to the owner only.
 *   3. `public` is visible to everyone (including guests, for posts).
 *   4. `friends` requires an accepted friendship.
 *   5. `friends_except` / `specific` consult the per-content allow/deny list.
 */
final class PrivacyService
{
    /**
     * @param  string  $subjectType  'post' | 'photo' | 'story' | profile field name
     */
    public function canSee(?User $viewer, Audience $audience, ?string $subjectId = null, string $subjectType = 'post', ?string $ownerId = null): bool
    {
        if ($audience === Audience::Public) {
            return $this->notBlocked($viewer, $ownerId);
        }

        if ($viewer === null) {
            return false;
        }

        if ($ownerId !== null && $this->isBlockedEitherWay($viewer->id, $ownerId)) {
            return false;
        }

        if ($ownerId !== null && $viewer->id === $ownerId) {
            return true;
        }

        if ($audience === Audience::OnlyMe) {
            return $ownerId !== null && $viewer->id === $ownerId;
        }

        if ($audience === Audience::Friends) {
            return $ownerId !== null && $this->areFriends($viewer->id, $ownerId);
        }

        if ($audience->requiresUserList() && $subjectId !== null) {
            return $this->passesList($viewer, $subjectType, $subjectId, $audience);
        }

        return false;
    }

    /**
     * Resolve the effective audience for a profile field, falling back to the
     * user's default when no explicit row exists.
     */
    public function fieldAudience(User $owner, string $field): Audience
    {
        $row = $owner->privacySettings()->where('field', $field)->first();

        return $row?->audienceEnum() ?? Audience::Public;
    }

    // ------------------------------------------------------------- internals

    private function passesList(User $viewer, string $subjectType, string $subjectId, Audience $audience): bool
    {
        $ids = $subjectType === 'post'
            ? $this->postAudienceIds($subjectId, $audience === Audience::Specific)
            : [];

        if ($ids === []) {
            // A deny list with nobody on it degenerates to "friends".
            return $audience === Audience::FriendsExcept;
        }

        return $audience === Audience::Specific
            ? in_array($viewer->id, $ids, true)
            : ! in_array($viewer->id, $ids, true);
    }

    /**
     * @return list<string>
     */
    private function postAudienceIds(string $postId, bool $allowed): array
    {
        return \Illuminate\Support\Facades\DB::table('post_audiences')
            ->where('post_id', $postId)
            ->where('is_allowed', $allowed)
            ->pluck('user_id')
            ->all();
    }

    private function areFriends(string $a, string $b): bool
    {
        $key = sprintf('friends:%s:%s', $a, $b);

        return Cache::remember($key, 300, fn (): bool => \App\Models\Friendship::query()
            ->where(fn ($q) => $q->where('user_id', $a)->where('friend_id', $b))
            ->orWhere(fn ($q) => $q->where('user_id', $b)->where('friend_id', $a))
            ->exists());
    }

    private function isBlockedEitherWay(string $a, string $b): bool
    {
        return \Illuminate\Support\Facades\DB::table('blocked_users')
            ->where(fn ($q) => $q->where('user_id', $a)->where('blocked_id', $b))
            ->orWhere(fn ($q) => $q->where('user_id', $b)->where('blocked_id', $a))
            ->exists();
    }

    private function notBlocked(?User $viewer, ?string $ownerId): bool
    {
        if ($viewer === null || $ownerId === null) {
            return true;
        }

        return ! $this->isBlockedEitherWay($viewer->id, $ownerId);
    }
}
