<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\ShipmentDocumentType;
use App\Models\Shipment;
use App\Models\SystemSetting;
use App\Support\ShipmentPdfSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

final class ShipmentLoadedNotification extends Notification implements ShouldQueue
{
    use Queueable, ShipmentPdfSupport;

    public function __construct(
        public readonly Shipment $shipment,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        // If the notifiable is the shipper of the shipment, we also send an email
        $shipperUserId = $this->shipment->shipper?->user_id;
        if ($shipperUserId !== null && (int) $notifiable->getKey() === (int) $shipperUserId) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $setting = SystemSetting::current()->loadMissing(['city', 'state']);
        $companyName = $setting->company_name ?: config('app.name');
        $cityName = $setting->city?->name;
        $stateName = $setting->state?->name;
        $location = collect([$cityName, $stateName])->filter()->implode(', ');
        $emailLogo = $setting->logoSrcForEmail();

        $mail = (new MailMessage)
            ->mailer($setting->getMailerFor('operations'))
            ->subject(__('Shipment Loaded on Vessel').' — '.$this->shipment->reference_no)
            ->markdown('emails.shipment-loaded', [
                'notifiable' => $notifiable,
                'shipment' => $this->shipment,
                'setting' => $setting,
                'companyName' => $companyName,
                'location' => $location,
                'emailLogo' => $emailLogo,
            ]);

        // Attach Invoice
        if ($this->shipment->invoice) {
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
                if (Storage::disk('public')->exists((string) $file->path)) {
                    $mail->attach(Storage::disk('public')->path((string) $file->path), [
                        'as' => (string) $file->original_name,
                    ]);
                }
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
            'title' => __('Shipment Loaded'),
            'body' => __('Shipment :ref has been marked as loaded on vessel.', [
                'ref' => $this->shipment->reference_no,
            ]),
            'shipment_id' => $this->shipment->id,
            'reference_no' => $this->shipment->reference_no,
            'url' => route('shipments.show', $this->shipment, absolute: true),
        ];
    }
}
