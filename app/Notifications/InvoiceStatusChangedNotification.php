<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\InvoiceStatus;
use App\Enums\ShipmentDocumentType;
use App\Models\Invoice;
use App\Models\Shipment;
use App\Models\SystemSetting;
use App\Modules\WhatsApp\Services\WhatsAppDocumentService;
use App\Notifications\Traits\HasWhatsAppNotification;
use App\Support\ShipmentPdfSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

final class InvoiceStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable, ShipmentPdfSupport, HasWhatsAppNotification;

    /** @var int Maximum seconds this job may run before timing out. */
    public int $timeout = 80;

    /** @var int Number of times to attempt the job. */
    public int $tries = 2;

    public function __construct(
        public readonly Shipment $shipment,
        public readonly Invoice $invoice,
        public readonly InvoiceStatus $fromStatus,
        public readonly InvoiceStatus $toStatus,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($this->toStatus === InvoiceStatus::Completed) {
            $channels[] = 'mail';
            $channels = $this->viaWithWhatsApp($channels, $notifiable, (int) $this->shipment->shipper_id);
        }

        return $channels;
    }

    public function toWhatsApp(object $notifiable): array
    {
        $docService = app(WhatsAppDocumentService::class);
        $files = [];

        // 1. Invoice PDF
        $files[] = $docService->getInvoicePayload($this->shipment);

        // 2. Bill of Lading
        $blDocument = $this->shipment->documents()
            ->where('document_type', ShipmentDocumentType::BillOfLading)
            ->latest()
            ->first();

        if ($blDocument) {
            foreach ($blDocument->files as $file) {
                $files[] = [
                    'url' => Storage::disk('public')->url($file->path),
                    'name' => $file->original_name ?? 'BillOfLading.pdf',
                ];
            }
        }

        return [
            'body' => "💰 *Invoice Completed:* Your shipment *{$this->shipment->reference_no}* is ready for release. Invoice and Bill of Lading attached.",
            'files' => $files,
            'related_entity' => $this->shipment,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        ini_set('memory_limit', '512M');

        $setting = SystemSetting::current()->loadMissing(['city', 'state']);
        $companyName = $setting->company_name ?: config('app.name');
        $cityName = $setting->city?->name;
        $stateName = $setting->state?->name;
        $location = collect([$cityName, $stateName])->filter()->implode(', ');
        $emailLogo = $setting->logoSrcForEmail();

        $mail = (new MailMessage)
            ->mailer($setting->getMailerFor('operations'))
            ->subject(__('Invoice Completed').' — '.$this->shipment->reference_no)
            ->markdown('emails.invoice-status-changed', [
                'notifiable' => $notifiable,
                'shipment' => $this->shipment,
                'invoice' => $this->invoice,
                'fromStatus' => $this->fromStatus,
                'toStatus' => $this->toStatus,
                'setting' => $setting,
                'companyName' => $companyName,
                'location' => $location,
                'emailLogo' => $emailLogo,
            ]);

        // Attach Invoice
        if ($this->invoice) {
            $invoicePdf = $this->generateInvoicePdf($this->shipment);
            $mail->attachData($invoicePdf->output(), 'Invoice_'.$this->shipment->reference_no.'.pdf', [
                'mime' => 'application/pdf',
            ]);
        }

        // Attach latest Bill of Lading files
        $blDocument = $this->shipment->documents()
            ->where('document_type', ShipmentDocumentType::BillOfLading)
            ->latest()
            ->first();

        if ($blDocument) {
            foreach ($blDocument->files as $file) {
                $mail->attach(Storage::disk('local')->path((string) $file->path), [
                    'as' => (string) $file->original_name,
                    'mime' => 'application/pdf',
                ]);
            }
        }

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Invoice Completed'),
            'body' => __('Invoice for shipment :ref changed from :from to :to.', [
                'ref' => $this->shipment->reference_no,
                'from' => $this->fromStatus->name,
                'to' => $this->toStatus->name,
            ]),
            'shipment_id' => $this->shipment->id,
            'reference_no' => $this->shipment->reference_no,
            'invoice_id' => $this->invoice->id,
            'from_status' => $this->fromStatus->value,
            'to_status' => $this->toStatus->value,
            'url' => route('shipments.show', $this->shipment, absolute: true),
        ];
    }
}
