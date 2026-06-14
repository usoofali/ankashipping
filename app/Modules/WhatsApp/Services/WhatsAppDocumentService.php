<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Services;

use App\Models\Shipment;
use App\Support\ShipmentPdfSupport;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WhatsAppDocumentService
{
    use ShipmentPdfSupport;

    /**
     * Generate a PDF and store it in the whatsapp-temp directory on the public disk.
     * Returns the public URL and the filename.
     *
     * @param  callable(): string  $pdfGenerator
     * @return array{url: string, name: string}
     */
    public function generateTempPdf(callable $pdfGenerator, string $filename): array
    {
        $tempPath = 'whatsapp-temp/'.Str::uuid().'_'.$filename;

        $pdfContent = $pdfGenerator();
        Storage::disk('public')->put($tempPath, $pdfContent);

        return [
            'url' => Storage::disk('public')->url($tempPath),
            'name' => $filename,
        ];
    }

    /**
     * Specific helper for Invoice PDF.
     */
    public function getInvoicePayload(Shipment $shipment): array
    {
        return $this->generateTempPdf(
            fn () => $this->generateInvoicePdf($shipment)->output(),
            "Invoice_{$shipment->reference_no}.pdf"
        );
    }

    /**
     * Specific helper for Dock Receipt PDF.
     */
    public function getDockReceiptPayload(Shipment $shipment): array
    {
        return $this->generateTempPdf(
            fn () => $this->generateDockReceiptPdf($shipment)->output(),
            "DockReceipt_{$shipment->reference_no}.pdf"
        );
    }
}
