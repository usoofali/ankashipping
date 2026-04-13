<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\ShippingMode;
use App\Models\Shipment;
use App\Models\SystemSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ShipmentCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Shipment $shipment,
        public readonly bool $isMerge = false,
        public readonly int $addedCount = 0,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        // Shipper receives both mail and database notifications
        if ($notifiable->shipper && (int) $notifiable->shipper->id === (int) $this->shipment->shipper_id) {
            return ['mail', 'database'];
        }

        // Staff/Admins only receive database notifications
        return ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $setting = SystemSetting::current()->loadMissing(['city', 'state']);
        $companyName = $setting->company_name ?: config('app.name');
        $cityName = $setting->city?->name;
        $stateName = $setting->state?->name;
        $location = collect([$cityName, $stateName])->filter()->implode(', ');
        $emailLogo = $setting->logoSrcForEmail();

        return (new MailMessage)
            ->mailer($setting->getMailerFor('operations'))
            ->subject($this->isMerge
                ? __('Vehicles Added to Container :ref – :count New Vehicles', ['ref' => $this->shipment->reference_no, 'count' => $this->addedCount])
                : ($this->shipment->shipping_mode === ShippingMode::Container
                    ? __('New Container Shipment Created – :count Vehicles', ['count' => $this->shipment->vehicles->count()])
                    : __('New RoRo Shipment Created – VIN :vin', ['vin' => $this->shipment->vehicles->first()?->vin])))
            ->markdown('emails.shipment-created', [
                'notifiable' => $notifiable,
                'shipment' => $this->shipment,
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
        return [
            'title' => $this->isMerge ? __('Vehicles Added to Container') : ($this->shipment->shipping_mode === ShippingMode::Container ? __('New Container Shipment') : __('New RoRo Shipment')),
            'body' => $this->isMerge
                ? __(':count new vehicles were added to existing container shipment :ref.', [
                    'count' => $this->addedCount,
                    'ref' => $this->shipment->reference_no,
                ])
                : ($this->shipment->shipping_mode === ShippingMode::Container
                    ? __('A new Container shipment (:ref) has been created for :count vehicles.', [
                        'ref' => $this->shipment->reference_no,
                        'count' => $this->shipment->vehicles->count(),
                    ])
                    : __('A new RoRo shipment (:ref) has been created for VIN :vin.', [
                        'ref' => $this->shipment->reference_no,
                        'vin' => $this->shipment->vehicles->first()?->vin,
                    ])),
            'shipment_id' => $this->shipment->id,
            'reference_no' => $this->shipment->reference_no,
            'url' => route('shipments.show', $this->shipment, absolute: true),
        ];
    }
}
