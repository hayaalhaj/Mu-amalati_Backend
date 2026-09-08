<?php

namespace App\Policies;

use App\Models\TransactionAttachment;
use App\Models\User;

class TransactionAttachmentPolicy
{
    /**
     * Determine whether the user can remove an attachment.
     */
    public function delete(User $user, TransactionAttachment $attachment): bool
    {
        $transaction = $attachment->transaction;

        if ($user->isAdmin()) {
            return true;
        }

        return $transaction
            && $transaction->created_by === $user->id
            && $transaction->status->isEditable();
    }
}
