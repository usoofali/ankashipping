<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Vehicle;
use App\Models\VehicleDocumentFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class VehicleDocumentFileDownloadResponder
{
    public static function stream(Vehicle $vehicle, VehicleDocumentFile $file): StreamedResponse
    {
        $file->loadMissing('vehicleDocument');

        if ($file->vehicleDocument === null || $file->vehicleDocument->vehicle_id !== $vehicle->id) {
            abort(404);
        }

        if (! Storage::disk('public')->exists($file->path)) {
            abort(404);
        }

        $downloadName = filled($file->original_name)
            ? (string) $file->original_name
            : basename($file->path);

        return Storage::disk('public')->download($file->path, $downloadName);
    }
}
