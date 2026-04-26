<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\SystemSetting;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

final class TitleDocumentAttachedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Vehicle $vehicle,
        public readonly VehicleDocument $vehicleDocument,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        // If the notifiable is the shipper of the shipment, we also send an email
        $shipperUserId = $this->vehicle->shipment?->shipper?->user_id;
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
            ->subject(__('Title Document Attached').' — '.$this->vehicle->vin)
            ->markdown('emails.title-document-attached', [
                'notifiable' => $notifiable,
                'vehicle' => $this->vehicle,
                'setting' => $setting,
                'companyName' => $companyName,
                'location' => $location,
                'emailLogo' => $emailLogo,
            ]);

        // Attach the files
        foreach ($this->vehicleDocument->files as $file) {
            $mail->attach(Storage::disk('public')->path((string) $file->path), [
                'as' => (string) $file->original_name,
            ]);
        }

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Title Document Attached'),
            'body' => __('A new title document has been attached to vehicle :vin.', [
                'vin' => $this->vehicle->vin,
            ]),
            'shipment_id' => $this->vehicle->shipment_id,
            'vehicle_id' => $this->vehicle->id,
            'reference_no' => $this->vehicle->shipment?->reference_no,
            'url' => route('shipments.show', $this->vehicle->shipment_id, absolute: true),
        ];
    }
}
