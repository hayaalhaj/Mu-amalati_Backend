<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can manage other users.
     */
    public function manage(User $actor, ?User $user = null): bool
    {
        return $actor->isAdmin();
    }

    /**
     * Determine whether the user can view another user.
     */
    public function view(User $actor, ?User $user = null): bool
    {
        return $actor->isAdmin() || ($user && $user->id === $actor->id);
    }
}
