<?php

declare(strict_types=1);

namespace App\Enums;

enum PrealertStatus: string
{
    case Pending = 'pending';
    case Converted = 'converted';
}
