<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

final class PagePolicy
{
    public function update(User $user, Page $page): bool
    {
        return $page->owner_id === $user->id
            || $page->roles()->where('user_id', $user->id)->whereIn('role', ['admin', 'editor'])->exists();
    }

    public function delete(User $user, Page $page): bool
    {
        return $page->owner_id === $user->id;
    }

    public function viewInsights(User $user, Page $page): bool
    {
        return $page->roles()->where('user_id', $user->id)->whereIn('role', ['admin', 'analyst'])->exists();
    }
}
