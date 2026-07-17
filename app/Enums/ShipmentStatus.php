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
    case TelexRequested = 'TELEX_REQUESTED';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Pending => __('Pending'),
            self::Dispatched => __('Dispatched'),
            self::Booking => __('Booking'),
            self::Inland => __('Inland'),
            self::Delivered => __('Delivered'),
            self::Loaded => __('Loaded'),
            self::TelexRequested => __('Telex Requested'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }
}
