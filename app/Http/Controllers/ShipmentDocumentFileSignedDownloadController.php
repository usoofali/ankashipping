<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\ShipmentDocumentFile;
use App\Support\ShipmentDocumentFileDownloadResponder;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ShipmentDocumentFileSignedDownloadController extends Controller
{
    public function __invoke(Shipment $shipment, ShipmentDocumentFile $file): StreamedResponse
    {
        return ShipmentDocumentFileDownloadResponder::stream($shipment, $file);
    }
}
