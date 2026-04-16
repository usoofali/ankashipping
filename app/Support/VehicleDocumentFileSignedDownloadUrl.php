<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Vehicle;
use App\Models\VehicleDocumentFile;
use Illuminate\Support\Facades\URL;

final class VehicleDocumentFileSignedDownloadUrl
{
    public static function for(Vehicle $vehicle, VehicleDocumentFile $file): string
    {
        $ttl = (int) config('shipment_documents.signed_download_ttl_seconds', 604800);

        return URL::temporarySignedRoute(
            'vehicles.documents.files.download.signed',
            now()->addSeconds(max(60, $ttl)),
            ['vehicle' => $vehicle->id, 'file' => $file->id],
        );
    }
}
