<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\ShipmentDocumentType;
use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\ShipmentDocument;
use App\Models\SystemSetting;
use App\Support\ShipmentDocumentSignedDownloadUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ShipmentDocumentAttachedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Shipment $shipment,
        public readonly ShipmentDocument $shipmentDocument,
        public readonly int $fileCount,
        public readonly ?ShipmentStatus $fromShipmentStatus,
        public readonly ?ShipmentStatus $toShipmentStatus,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $shipperUserId = $this->shipment->shipper?->user_id;

        if ($shipperUserId !== null && (int) $notifiable->getKey() === (int) $shipperUserId) {
            return ['mail', 'database'];
        }

        return ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->shipment->refresh();
        $this->shipmentDocument->refresh();
        $this->shipmentDocument->loadMissing('files');

        $setting = SystemSetting::current()->loadMissing(['city', 'state']);
        $companyName = $setting->company_name ?: config('app.name');
        $cityName = $setting->city?->name;
        $stateName = $setting->state?->name;
        $location = collect([$cityName, $stateName])->filter()->implode(', ');
        $emailLogo = $setting->logoSrcForEmail();

        $docType = $this->shipmentDocument->document_type;
        $docLabel = $docType instanceof ShipmentDocumentType ? $docType->label() : __('Document');

        $notificationTitle = self::documentAttachedTitle($docLabel);

        $downloadLinks = [];
        foreach ($this->shipmentDocument->files as $file) {
            $downloadLinks[] = [
                'name' => filled($file->original_name) ? (string) $file->original_name : basename($file->path),
                'url' => ShipmentDocumentSignedDownloadUrl::for($this->shipment, $file),
            ];
        }

        return (new MailMessage)
            ->mailer($setting->getMailerFor('operations'))
            ->subject($notificationTitle.' — '.$this->shipment->reference_no)
            ->markdown('emails.shipment-document-attached', [
                'notifiable' => $notifiable,
                'shipment' => $this->shipment,
                'documentLabel' => $docLabel,
                'notificationTitle' => $notificationTitle,
                'fileCount' => $this->fileCount,
                'downloadLinks' => $downloadLinks,
                'fromShipmentStatus' => $this->fromShipmentStatus,
                'toShipmentStatus' => $this->toShipmentStatus,
                'setting' => $setting,
                'companyName' => $companyName,
                'location' => $location,
                'emailLogo' => $emailLogo,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->shipment->refresh();
        $this->shipmentDocument->refresh();
        $this->shipmentDocument->loadMissing('files');

        $docType = $this->shipmentDocument->document_type;
        $docLabel = $docType instanceof ShipmentDocumentType ? $docType->label() : __('Document');

        $notificationTitle = self::documentAttachedTitle($docLabel);

        $body = __('Document :type attached to shipment :ref (:count file(s)).', [
            'type' => $docLabel,
            'ref' => $this->shipment->reference_no,
            'count' => $this->fileCount,
        ]);

        if ($this->fromShipmentStatus !== null && $this->toShipmentStatus !== null && $this->fromShipmentStatus !== $this->toShipmentStatus) {
            $body .= ' '.__('Status: :from → :to.', [
                'from' => $this->fromShipmentStatus->name,
                'to' => $this->toShipmentStatus->name,
            ]);
        }

        $downloadUrls = [];
        foreach ($this->shipmentDocument->files as $file) {
            $downloadUrls[] = [
                'name' => filled($file->original_name) ? (string) $file->original_name : basename($file->path),
                'url' => ShipmentDocumentSignedDownloadUrl::for($this->shipment, $file),
            ];
        }

        return [
            'title' => $notificationTitle,
            'body' => $body,
            'shipment_id' => $this->shipment->id,
            'reference_no' => $this->shipment->reference_no,
            'shipment_document_id' => $this->shipmentDocument->id,
            'document_type' => $docType instanceof ShipmentDocumentType ? $docType->value : null,
            'document_label' => $docLabel,
            'shipment_status' => $this->shipment->shipment_status?->value,
            'from_shipment_status' => $this->fromShipmentStatus?->value,
            'to_shipment_status' => $this->toShipmentStatus?->value,
            'url' => route('shipments.show', $this->shipment, absolute: true),
            'download_urls' => $downloadUrls,
        ];
    }

    public static function documentAttachedTitle(string $documentLabel): string
    {
        return __(':document attached', ['document' => $documentLabel]);
    }
}
