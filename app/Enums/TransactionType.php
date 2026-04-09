<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionType: string
{
    case Credit = 'credit';
    case Debit = 'debit';
    case Adjustment = 'adjustment';
}
