<?php

declare(strict_types=1);

namespace App\Enums;

enum ShipmentDocumentType: string
{
    case BillOfLading = 'bill-of-lading';
    case TitleDocument = 'title-document';
    case StampDockReceipt = 'stamp-dock-receipt';
    case PhotosAndVideos = 'photos-and-videos';

    public function label(): string
    {
        return match ($this) {
            self::BillOfLading => __('Bill of lading'),
            self::TitleDocument => __('Title document'),
            self::StampDockReceipt => __('Stamp dock receipt'),
            self::PhotosAndVideos => __('Photos and videos'),
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
            self::TitleDocument => ShipmentStatus::Inland,
            self::StampDockReceipt => ShipmentStatus::DeliveredToPort,
            self::BillOfLading => ShipmentStatus::CargoLoaded,
            default => null,
        };
    }
}
