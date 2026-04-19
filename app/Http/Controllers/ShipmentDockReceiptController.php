<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\SystemSetting;
use App\ShippingWorkflow\ShippingWorkflow;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

final class ShipmentDockReceiptController extends Controller
{
    use AuthorizesRequests;

    public function download(Shipment $shipment): Response
    {
        $this->authorize('shipments.view', $shipment);
        $this->authorize('workflow.download_dock_receipt');

        // Workflow Guard
        if (! app(ShippingWorkflow::class)->canDownloadDockReceipt($shipment, auth()->user())) {
            abort(403, __('Dock Receipt cannot be generated until logistics information is complete.'));
        }

        $shipment->load([
            'shipper.user',
            'shipper.country',
            'shipper.state',
            'shipper.city',
            'consignee.country',
            'consignee.state',
            'notifyParty.country',
            'notifyParty.state',
            'originPort.state',
            'originPort.country',
            'destinationPort.state',
            'destinationPort.country',
            'vehicles',
            'carrier',
        ]);

        $settings = SystemSetting::current();
        $settings->loadMissing(['city', 'state', 'country']);

        // QR Code Generation (Consistent with Invoice)
        $qrText = url('/track/'.$shipment->reference_no);
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data='.urlencode($qrText);
        $qrCodeBase64 = null;

        try {
            $qrResponse = Http::get($qrUrl);
            if ($qrResponse->successful()) {
                $qrCodeBase64 = 'data:image/png;base64,'.base64_encode($qrResponse->body());
            }
        } catch (\Exception $e) {
            // Skip QR if API fails
        }

        $pdf = Pdf::loadView('pdf.dock_receipt', [
            'shipment' => $shipment,
            'settings' => $settings,
            'qrCode' => $qrCodeBase64,
        ]);

        return $pdf->download('DockReceipt_'.$shipment->reference_no.'.pdf');
    }
}
