<?php

declare(strict_types=1);

namespace App\Enums;

enum ShipmentDocumentType: string
{
    case StampDockReceipt = 'stamped-dock-receipt';
    case BillOfLading = 'bill-of-lading';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::StampDockReceipt => __('Stamped dock receipt'),
            self::BillOfLading => __('Bill of lading'),
            self::Other => __('Other'),
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }

    /**
     * Shipment status to apply after a successful attach batch of this document type.
     */
    public function targetShipmentStatusAfterAttachment(): ?ShipmentStatus
    {
        return match ($this) {
            self::StampDockReceipt => ShipmentStatus::Delivered,
            self::BillOfLading => ShipmentStatus::Loaded,
            default => null,
        };
    }
}
