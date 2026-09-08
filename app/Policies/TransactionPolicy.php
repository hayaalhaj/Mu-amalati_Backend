<?php

namespace App\Policies;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    /**
     * Determine whether the user can view the transaction.
     */
    public function view(
        User $user,
        Transaction $transaction
    ): bool {
        if ($user->isAdmin()) {
            return true;
        }

        if ($transaction->created_by === $user->id) {
            return true;
        }

        /*
         * A manager can view a transaction if one of
         * its workflow departments is managed by that manager.
         *
         * This allows managers to keep viewing transactions
         * they already reviewed, even after the workflow
         * moves to another department.
         */
        if ($user->isManager()) {
            return $transaction->workflowSteps()
                ->whereIn(
                    'department_id',
                    $user->managedDepartmentIds()
                )
                ->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can create a transaction.
     */
    public function create(User $user): bool
    {
        return $user->isEmployee()
            || $user->isManager();
    }

    /**
     * Determine whether the user can update the transaction.
     */
    public function update(
        User $user,
        Transaction $transaction
    ): bool {
        return $transaction->created_by === $user->id
            && $transaction->status->isEditable();
    }

    /**
     * Determine whether the user can delete the transaction.
     */
    public function delete(
        User $user,
        Transaction $transaction
    ): bool {
        return $transaction->created_by === $user->id
            && $transaction->status === TransactionStatus::Draft;
    }

    /**
     * Determine whether the user can submit/resubmit the transaction.
     */
    public function submit(
        User $user,
        Transaction $transaction
    ): bool {
        return $transaction->created_by === $user->id
            && (
                $transaction->status === TransactionStatus::Draft
                || $transaction->status === TransactionStatus::Returned
            );
    }

    /**
     * Determine whether the user can review
     * (approve/return/reject) the transaction.
     */
    public function review(
        User $user,
        Transaction $transaction
    ): bool {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->isAuthorizedReviewer(
            $user,
            $transaction
        );
    }

    /**
     * Determine whether the user can add or remove attachments.
     */
    public function manageAttachments(
        User $user,
        Transaction $transaction
    ): bool {
        if ($user->isAdmin()) {
            return true;
        }

        return $transaction->created_by === $user->id
            && $transaction->status->isEditable();
    }

    /**
     * Determine whether the user is an authorized reviewer
     * for the transaction's CURRENT workflow step.
     */
    protected function isAuthorizedReviewer(
        User $user,
        Transaction $transaction
    ): bool {
        if (! $user->isManager()) {
            return false;
        }

        if (! $transaction->current_department_id) {
            return false;
        }

        return $user->managedDepartments()
            ->where(
                'id',
                $transaction->current_department_id
            )
            ->exists();
    }
}