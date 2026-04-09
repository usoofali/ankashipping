<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Shipment;
use App\Models\ShipmentDocumentFile;
use Illuminate\Support\Facades\URL;

final class ShipmentDocumentSignedDownloadUrl
{
    public static function for(Shipment $shipment, ShipmentDocumentFile $file): string
    {
        $ttl = (int) config('shipment_documents.signed_download_ttl_seconds', 604800);

        return URL::temporarySignedRoute(
            'shipments.documents.files.download.signed',
            now()->addSeconds(max(60, $ttl)),
            ['shipment' => $shipment->id, 'file' => $file->id],
        );
    }
}
