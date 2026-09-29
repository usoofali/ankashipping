<?php

declare(strict_types=1);

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Cleared = 'cleared';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Cleared => __('Cleared'),
            self::Completed => __('Completed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'amber',
            self::Cleared => 'sky',
            self::Completed => 'emerald',
        };
    }
}
