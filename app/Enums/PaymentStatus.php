<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case AwaitingBL = 'awaiting_bl';
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingBL => __('Awaiting B/L'),
            self::AwaitingPayment => __('Awaiting Payment'),
            self::Paid => __('Paid'),
        };
    }
}
