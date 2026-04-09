<?php

declare(strict_types=1);

namespace App\Enums;

enum ShippingMode: string
{
    case Roro = 'roro';
    case Container = 'container';
    case ExpeditedShipping = 'expedited_shipping';
}
