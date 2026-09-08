<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;

class TransactionNotificationService
{
    /**
     * Create a database notification for a user.
     */
    public function create(
        User $user,
        Transaction $transaction,
        string $title,
        string $message,
        string $type
    ): Notification {
        return Notification::create([
            'user_id' => $user->id,
            'transaction_id' => $transaction->id,
            'title' => $title,
            'message' => $message,
            'type' => $type,
        ]);
    }

    /**
     * Notify the manager of a department that a review is required.
     */
    public function notifyManagerForReview(Transaction $transaction, int $departmentId): void
    {
        $manager = Department::find($departmentId)?->manager;

        if (! $manager || ! $manager->isActive()) {
            return;
        }

        $this->create(
            $manager,
            $transaction,
            'Review required',
            sprintf('Transaction %s requires your review.', $transaction->transaction_number),
            'review_required'
        );
    }

    /**
     * Notify the transaction creator about a state change.
     */
    public function notifyCreator(Transaction $transaction, string $type, string $actionLabel): void
    {
        $creator = $transaction->creator;

        if (! $creator || ! $creator->isActive()) {
            return;
        }

        $this->create(
            $creator,
            $transaction,
            ucfirst($actionLabel),
            sprintf('Your transaction %s was %s.', $transaction->transaction_number, $actionLabel),
            $type
        );
    }
}
