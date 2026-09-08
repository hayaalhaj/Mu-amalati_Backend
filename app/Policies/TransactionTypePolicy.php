<?php

namespace App\Policies;

use App\Models\TransactionType;
use App\Models\User;

class TransactionTypePolicy
{
    /**
     * Determine whether the user can manage transaction types.
     */
    public function manage(User $user, ?TransactionType $type = null): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view a transaction type.
     */
    public function view(User $user, ?TransactionType $type = null): bool
    {
        return true;
    }
}
