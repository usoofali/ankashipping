<?php

declare(strict_types=1);

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Cleared = 'cleared';
    case Completed = 'completed';
}
