<?php

declare(strict_types=1);

namespace App\Enums;

enum VehicleIs: string
{
    case Runner = 'runner';
    case NonRunner = 'non-runner';
    case Forklift = 'forklift';

    public function label(): string
    {
        return match ($this) {
            self::Runner => __('Runner'),
            self::NonRunner => __('Non-runner'),
            self::Forklift => __('Forklift'),
        };
    }
}
