<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\SystemSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

final class ShipmentInvoiceController extends Controller
{
    use AuthorizesRequests;

    public function download(Shipment $shipment): Response
    {
        $this->authorize('shipments.view', $shipment);

        $shipment->load([
            'shipper.user',
            'shipper.country',
            'shipper.state',
            'shipper.city',
            'consignee',
            'originPort.state',
            'originPort.country',
            'destinationPort.state',
            'destinationPort.country',
            'vehicle',
            'carrier',
            'paymentMethod',
            'invoice.items',
            'invoice.payment',
        ]);

        if (! $shipment->invoice) {
            abort(404, __('Invoice not found for this shipment.'));
        }

        $settings = SystemSetting::current();
        $settings->loadMissing(['city', 'state', 'country']);

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

        $pdf = Pdf::loadView('pdf.invoice', [
            'shipment' => $shipment,
            'settings' => $settings,
            'invoice' => $shipment->invoice,
            'qrCode' => $qrCodeBase64,
        ]);

        return $pdf->download('Invoice:'.$shipment->reference_no.'.pdf');
    }
}
