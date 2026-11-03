<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SavedCollection;
use App\Models\User;

final class UserPolicy
{
    /**
     * Only the owner may touch their saved collections and friend lists.
     */
    public function update(User $user, SavedCollection|User $model): bool
    {
        return $model instanceof SavedCollection
            ? $model->user_id === $user->id
            : $model->id === $user->id;
    }

    public function delete(User $user, SavedCollection|User $model): bool
    {
        return $this->update($user, $model);
    }

    public function manageContent(User $user): bool
    {
        // Reserved for future moderator roles; kept explicit so `can.delete`
        // in the PostResource has a single place to grow.
        return false;
    }
}
