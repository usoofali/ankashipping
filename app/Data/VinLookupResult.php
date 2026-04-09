<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\VinLookupOutcome;
use App\Models\Shipment;
use App\Models\Vehicle;

final class VinLookupResult
{
    public function __construct(
        public readonly VinLookupOutcome $outcome,
        public readonly ?Vehicle $vehicle = null,
        public readonly ?Shipment $shipment = null,
        public readonly ?string $message = null,
        public readonly ?int $apiRequestsLeft = null,
        public readonly bool $belongsToAnotherShipper = false,
    ) {}

    public static function alreadyOnShipment(
        Vehicle $vehicle,
        Shipment $shipment,
        bool $belongsToAnotherShipper = false,
        ?string $message = null,
    ): self {
        return new self(
            outcome: VinLookupOutcome::AlreadyOnShipment,
            vehicle: $vehicle,
            shipment: $shipment,
            message: $message,
            belongsToAnotherShipper: $belongsToAnotherShipper,
        );
    }

    public static function vehicleReady(Vehicle $vehicle): self
    {
        return new self(
            outcome: VinLookupOutcome::VehicleReady,
            vehicle: $vehicle,
        );
    }

    public static function fetchedFromApi(Vehicle $vehicle, ?int $apiRequestsLeft = null): self
    {
        return new self(
            outcome: VinLookupOutcome::FetchedFromApi,
            vehicle: $vehicle,
            apiRequestsLeft: $apiRequestsLeft,
        );
    }

    public static function vinInvalid(?string $message = null): self
    {
        return new self(
            outcome: VinLookupOutcome::VinInvalid,
            message: $message ?? __('The VIN format is invalid.'),
        );
    }

    public static function vinNotFound(?string $message = null): self
    {
        return new self(
            outcome: VinLookupOutcome::VinNotFound,
            message: $message ?? __('No vehicle data was found for this VIN.'),
        );
    }

    public static function apiFailed(?string $message = null): self
    {
        return new self(
            outcome: VinLookupOutcome::ApiFailed,
            message: $message ?? __('Unable to reach the VIN lookup service. Try again later.'),
        );
    }

    public static function rateLimited(?string $message = null): self
    {
        return new self(
            outcome: VinLookupOutcome::RateLimited,
            message: $message ?? __('Too many VIN lookups. Please wait a minute and try again.'),
        );
    }
}
