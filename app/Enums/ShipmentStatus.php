<?php

declare(strict_types=1);

namespace App\Enums;

enum ShipmentStatus: string
{
    case Pending = 'pending';
    case Dispatched = 'dispatched';
    case Inland = 'inland';
    case AtWorkshop = 'at_workshop';
    case DeliveredToPort = 'delivered_to_port';
    case CargoLoaded = 'cargo_loaded';
}
