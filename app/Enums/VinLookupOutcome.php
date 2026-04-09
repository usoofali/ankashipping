<?php

declare(strict_types=1);

namespace App\Enums;

enum VinLookupOutcome: string
{
    case AlreadyOnShipment = 'already_on_shipment';
    case VehicleReady = 'vehicle_ready';
    case FetchedFromApi = 'fetched_from_api';
    case VinInvalid = 'vin_invalid';
    case VinNotFound = 'vin_not_found';
    case ApiFailed = 'api_failed';
    case RateLimited = 'rate_limited';
}
