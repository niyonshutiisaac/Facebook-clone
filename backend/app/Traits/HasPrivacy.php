<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\Audience;
use App\Models\User;

/**
 * Audience resolution shared by posts, photos and profile fields.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasPrivacy
{
    public function audienceEnum(): Audience
    {
        return Audience::tryFrom((string) $this->audience) ?? Audience::Public;
    }

    /**
     * Is this content visible to the given viewer?
     *
     * The heavy lifting (friendship, blocks, per-post allow/deny lists) lives
     * in App\Services\Social\PrivacyService so it can be unit tested and
     * reused for eager-loaded collections without N+1 queries.
     */
    public function isVisibleTo(?User $viewer): bool
    {
        /** @var \App\Services\Social\PrivacyService $privacy */
        $privacy = app(\App\Services\Social\PrivacyService::class);

        return $privacy->canSee($viewer, $this->audienceEnum(), $this->getKey(), 'post');
    }
}
