<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\VinLookupResult;
use App\Models\Shipment;
use App\Models\Vehicle;
use App\Support\MapCopartApiVehicleItemToVehicleAttributes;
use App\Support\VinNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class VinLookupService
{
    public function __construct(
        private readonly VinAuctionApiClient $apiClient,
    ) {}

    /**
     * Resolves a VIN using DB state first, then the Copart/IAAI RapidAPI when needed.
     * Rate limiting applies only before an outbound API request.
     */
    public function lookup(string $rawVin, int $shipperId): VinLookupResult
    {
        $vin = VinNormalizer::normalize($rawVin);

        if ($vin === '' || ! VinNormalizer::isValidFormat($vin)) {
            return VinLookupResult::vinInvalid();
        }

        $vehicle = Vehicle::query()->where('vin', $vin)->first();

        $shipment = $vehicle instanceof Vehicle ? $vehicle->shipment : null;

        if ($vehicle instanceof Vehicle && $shipment instanceof Shipment) {
            if ($shipment instanceof Shipment) {
                $other = $shipment->shipper_id !== $shipperId;

                return VinLookupResult::alreadyOnShipment(
                    vehicle: $vehicle,
                    shipment: $shipment,
                    belongsToAnotherShipper: $other,
                    message: $other
                        ? __('This VIN is already linked to an active shipment.')
                        : __('This vehicle is already registered on a shipment.'),
                );
            }
        }

        if ($vehicle instanceof Vehicle) {
            return VinLookupResult::vehicleReady($vehicle);
        }

        $rateKey = 'vin-api:shipper:'.$shipperId;
        $maxAttempts = (int) config('services.copart_iaai.rate_limit_per_minute', 30);

        /** @var VinLookupResult|false $resolved */
        $resolved = RateLimiter::attempt(
            $rateKey,
            $maxAttempts,
            function () use ($vin): VinLookupResult {
                try {
                    $envelope = $this->apiClient->searchVin($vin);
                } catch (RequestException $e) {
                    if ($e->response->status() === 429) {
                        return VinLookupResult::rateLimited();
                    }

                    Log::warning('vin_lookup_api_failed', [
                        'vin' => $vin,
                        'status' => $e->response->status(),
                        'message' => $e->getMessage(),
                    ]);

                    return VinLookupResult::apiFailed();
                } catch (\Throwable $e) {
                    Log::warning('vin_lookup_api_exception', [
                        'vin' => $vin,
                        'message' => $e->getMessage(),
                    ]);

                    return VinLookupResult::apiFailed();
                }

                $apiRequestsLeft = isset($envelope['api_request_left']) ? (int) $envelope['api_request_left'] : null;
                $data = $envelope['data'] ?? null;
                if (! is_array($data) || empty($data)) {
                    return VinLookupResult::vinNotFound();
                }

                $attributes = MapCopartApiVehicleItemToVehicleAttributes::map($data, $envelope);

                try {
                    $created = Vehicle::query()->create($attributes);
                } catch (QueryException $e) {
                    if (str_contains(strtolower($e->getMessage()), 'unique')) {
                        $existing = Vehicle::query()->where('vin', $vin)->first();
                        if ($existing instanceof Vehicle) {
                            return VinLookupResult::vehicleReady($existing);
                        }
                    }

                    throw $e;
                }

                return VinLookupResult::fetchedFromApi($created, $apiRequestsLeft);
            },
            60,
        );

        if ($resolved === false) {
            return VinLookupResult::rateLimited();
        }

        return $resolved;
    }
}
