<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case AwaitingBL = 'awaiting_bl';
    case Paid = 'paid';
}
