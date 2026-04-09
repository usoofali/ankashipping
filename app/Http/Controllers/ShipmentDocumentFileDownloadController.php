<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\ShipmentDocumentFile;
use App\Support\ShipmentDocumentFileDownloadResponder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ShipmentDocumentFileDownloadController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Shipment $shipment, ShipmentDocumentFile $file): StreamedResponse
    {
        $this->authorize('shipments.view', $shipment);

        return ShipmentDocumentFileDownloadResponder::stream($shipment, $file);
    }
}
