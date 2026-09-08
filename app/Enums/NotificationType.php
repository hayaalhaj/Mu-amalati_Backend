<?php

namespace App\Enums;

enum NotificationType: string
{
    case TransactionSubmitted = 'transaction_submitted';
    case TransactionApproved = 'transaction_approved';
    case TransactionReturned = 'transaction_returned';
    case TransactionRejected = 'transaction_rejected';
    case TransactionCompleted = 'transaction_completed';
    case ReviewRequired = 'review_required';
}
