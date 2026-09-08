<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Returned = 'returned';
    case Rejected = 'rejected';
    case Approved = 'approved';
    case Completed = 'completed';

    /**
     * Determine if the transaction is editable by its creator.
     */
    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Returned;
    }

    /**
     * Determine if the transaction is still in progress.
     */
    public function isActive(): bool
    {
        return in_array($this, [self::Draft, self::Pending, self::Returned], true);
    }
}
