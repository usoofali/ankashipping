<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Shipment;
use App\Models\SystemSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;

trait ShipmentPdfSupport
{
    protected function generateInvoicePdf(Shipment $shipment)
    {
        $shipment->loadMissing(['shipper.user', 'shipper.country', 'shipper.state', 'shipper.city', 'consignee.country', 'consignee.state', 'invoice.items', 'vehicles']);
        $invoice = $shipment->invoice;
        $settings = SystemSetting::current();
        $settings->loadMissing(['city', 'state', 'country']);

        $qrCodeBase64 = $this->generateQrCode($shipment);

        return Pdf::loadView('pdf.invoice', [
            'shipment' => $shipment,
            'invoice' => $invoice,
            'settings' => $settings,
            'qrCode' => $qrCodeBase64,
        ]);
    }

    protected function generateDockReceiptPdf(Shipment $shipment)
    {
        $shipment->loadMissing([
            'shipper.user', 'shipper.country', 'shipper.state', 'shipper.city',
            'consignee.country', 'consignee.state',
            'notifyParty.country', 'notifyParty.state',
            'originPort.state', 'originPort.country',
            'destinationPort.state', 'destinationPort.country',
            'vehicles', 'carrier',
        ]);

        $settings = SystemSetting::current();
        $settings->loadMissing(['city', 'state', 'country']);

        $qrCodeBase64 = $this->generateQrCode($shipment);

        return Pdf::loadView('pdf.dock_receipt', [
            'shipment' => $shipment,
            'settings' => $settings,
            'qrCode' => $qrCodeBase64,
        ]);
    }

    protected function generateQrCode(Shipment $shipment): ?string
    {
        $qrText = url('/track/'.$shipment->reference_no);
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data='.urlencode($qrText);
        $qrCodeBase64 = null;

        try {
            $qrResponse = Http::connectTimeout(5)->timeout(10)->get($qrUrl);
            if ($qrResponse->successful()) {
                $qrCodeBase64 = 'data:image/png;base64,'.base64_encode($qrResponse->body());
            }
        } catch (\Exception $e) {
            // Skip — QR code is optional; PDF will render without it.
        }

        return $qrCodeBase64;
    }
}
