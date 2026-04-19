<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Support\ShipmentPdfSupport;
use Symfony\Component\HttpFoundation\Response;

final class ShipmentDockReceiptSignedDownloadController extends Controller
{
    use ShipmentPdfSupport;

    public function __invoke(Shipment $shipment): Response
    {
        $pdf = $this->generateDockReceiptPdf($shipment);

        return $pdf->download('DockReceipt_'.$shipment->reference_no.'.pdf');
    }
}
