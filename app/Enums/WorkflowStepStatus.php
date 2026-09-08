<?php

namespace App\Enums;

enum WorkflowStepStatus: string
{
    case Waiting = 'waiting';
    case Pending = 'pending';
    case Approved = 'approved';
    case Returned = 'returned';
    case Rejected = 'rejected';
    case Skipped = 'skipped';
}
