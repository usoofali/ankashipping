<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Shipment;
use App\Models\ShipmentDocument;
use App\Models\SystemSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

final class StampedDockReceiptNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Shipment $shipment,
        public readonly ShipmentDocument $shipmentDocument,
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
            ->subject(__('Stamped Dock Receipt Available').' — '.$this->shipment->reference_no)
            ->markdown('emails.stamped-dock-receipt-attached', [
                'notifiable' => $notifiable,
                'shipment' => $this->shipment,
                'setting' => $setting,
                'companyName' => $companyName,
                'location' => $location,
                'emailLogo' => $emailLogo,
            ]);

        // Attach the files
        foreach ($this->shipmentDocument->files as $file) {
            $mail->attach(Storage::disk('local')->path((string) $file->path), [
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
            'title' => __('Stamped Dock Receipt Attached'),
            'body' => __('A stamped dock receipt has been attached to shipment :ref.', [
                'ref' => $this->shipment->reference_no,
            ]),
            'shipment_id' => $this->shipment->id,
            'reference_no' => $this->shipment->reference_no,
            'url' => route('shipments.show', $this->shipment, absolute: true),
        ];
    }
}
