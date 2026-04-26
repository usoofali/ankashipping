<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Shipment;
use App\Models\ShipmentDocumentFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ShipmentDocumentFileDownloadResponder
{
    public static function stream(Shipment $shipment, ShipmentDocumentFile $file): StreamedResponse
    {
        $file->loadMissing('shipmentDocument');

        if ($file->shipmentDocument === null || $file->shipmentDocument->shipment_id !== $shipment->id) {
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
