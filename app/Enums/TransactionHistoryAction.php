<?php

namespace App\Enums;

enum TransactionHistoryAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Submitted = 'submitted';
    case Resubmitted = 'resubmitted';
    case ApprovedStep = 'approved_step';
    case Returned = 'returned';
    case Rejected = 'rejected';
    case FullyApproved = 'fully_approved';
    case Completed = 'completed';
    case AttachmentAdded = 'attachment_added';
    case AttachmentRemoved = 'attachment_removed';
}
