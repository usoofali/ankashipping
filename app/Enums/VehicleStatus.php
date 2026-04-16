<?php

declare(strict_types=1);

namespace App\Enums;

enum VehicleStatus: string
{
    case Pending = 'PENDING';
    case Dispatched = 'DISPATCHED';
    case Inland = 'INLAND';
    case AtWarehouse = 'AT_WAREHOUSE';
}
