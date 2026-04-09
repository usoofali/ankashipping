<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Shipment;
use Illuminate\Notifications\Notification;

final class ShipmentDispatchedNotification extends Notification
{
    public function __construct(
        public readonly Shipment $shipment,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Shipment dispatched'),
            'body' => __('Shipment :ref has been dispatched (driver assigned).', [
                'ref' => $this->shipment->reference_no,
            ]),
            'shipment_id' => $this->shipment->id,
            'reference_no' => $this->shipment->reference_no,
            'vin' => $this->shipment->vin,
            'url' => route('shipments.show', $this->shipment, absolute: true),
        ];
    }
}
