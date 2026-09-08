<?php

namespace App\Enums;

enum TransactionPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
}
