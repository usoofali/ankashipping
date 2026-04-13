<?php

declare(strict_types=1);

namespace App\Enums;

enum VehicleStatus: string
{
    case Pending = 'pending';
    case Dispatched = 'dispatched';
    case InlandTransit = 'inland_transit';
    case AtWorkshop = 'at_workshop';
    case ArrivedAtWarehouse = 'arrived_at_warehouse';
    case Loaded = 'loaded';
}
