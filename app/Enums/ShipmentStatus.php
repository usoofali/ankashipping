<?php

declare(strict_types=1);

namespace App\Enums;

enum ShipmentStatus: string
{
    case Open = 'OPEN';
    case Pending = 'PENDING';
    case Dispatched = 'DISPATCHED';
    case Booking = 'BOOKING';
    case Inland = 'INLAND';
    case Delivered = 'DELIVERED';
    case Loaded = 'LOADED';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
}
