<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Services\VinAuctionApiClient;
use App\Support\MapCopartApiVehicleItemToVehicleAttributes;
use App\Support\VinNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class VehiclePictureController extends Controller
{
    public function __construct(
        private readonly VinAuctionApiClient $apiClient,
    ) {}

    public function show(Request $request, ?string $vin = null): JsonResponse
    {
        $rawVin = $vin ?? $request->query('vin');

        if (! is_string($rawVin) || trim($rawVin) === '') {
            return response()->json([
                'success' => false,
                'message' => __('A valid VIN parameter is required.'),
                'pictures' => [],
            ], 422);
        }

        $normalizedVin = VinNormalizer::normalize($rawVin);

        if ($normalizedVin === '' || ! VinNormalizer::isValidFormat($normalizedVin)) {
            return response()->json([
                'success' => false,
                'message' => __('The provided VIN format is invalid.'),
                'pictures' => [],
            ], 422);
        }

        // 1. Try local database first
        $vehicle = Vehicle::findByVin($normalizedVin);

        if ($vehicle instanceof Vehicle) {
            $pictures = $this->collectVehiclePictures($vehicle);

            if (! empty($pictures)) {
                return response()->json([
                    'success' => true,
                    'vin' => $vehicle->vin,
                    'make' => $vehicle->make,
                    'model' => $vehicle->model,
                    'year' => $vehicle->year,
                    'pictures' => $pictures,
                ]);
            }
        }

        // 2. Fallback to Copart/IAAI RapidAPI client lookup
        try {
            $envelope = $this->apiClient->searchVin($normalizedVin);
            $data = $envelope['data'] ?? null;

            if (is_array($data) && ! empty($data)) {
                $attributes = MapCopartApiVehicleItemToVehicleAttributes::map($data, $envelope);
                $photos = $data['lots'][0]['images']['normal'] ?? $data['lots'][0]['images']['small'] ?? [];
                $photoUrls = array_values(array_filter((array) $photos, 'is_string'));

                return response()->json([
                    'success' => true,
                    'vin' => $attributes['vin'] ?? $normalizedVin,
                    'make' => $attributes['make'] ?? null,
                    'model' => $attributes['model'] ?? null,
                    'year' => $attributes['year'] ?? null,
                    'pictures' => array_values($photoUrls),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('vehicle_picture_api_error', [
                'vin' => $normalizedVin,
                'error' => $e->getMessage(),
            ]);
        }

        // If local vehicle exists without photos, return basic details with empty pictures
        if ($vehicle instanceof Vehicle) {
            return response()->json([
                'success' => true,
                'vin' => $vehicle->vin,
                'make' => $vehicle->make,
                'model' => $vehicle->model,
                'year' => $vehicle->year,
                'pictures' => [],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => __('No vehicle or pictures found for the specified VIN.'),
            'pictures' => [],
        ], 404);
    }

    /**
     * @return list<string>
     */
    private function collectVehiclePictures(Vehicle $vehicle): array
    {
        $urls = $vehicle->copartCarPhotoUrls();

        // Also gather any uploaded vehicle document files that are images
        $vehicle->loadMissing('vehicleDocuments.files');

        foreach ($vehicle->vehicleDocuments as $doc) {
            foreach ($doc->files as $file) {
                if ($file->isImage() && $file->storage_path) {
                    $urls[] = route('vehicles.documents.files.download.signed', [
                        'vehicle' => $vehicle->id,
                        'file' => $file->id,
                    ]);
                }
            }
        }

        return array_values(array_unique(array_filter($urls)));
    }
}
