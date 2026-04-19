<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Driver;
use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class DriverAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Shipment $shipment,
        public readonly Driver $driver,
    ) {}

    /**
     * @return array<int, string>
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
            'title' => __('Driver Assigned'),
            'body' => __('Driver :driver has been assigned to shipment :ref.', [
                'driver' => $this->driver->name,
                'ref' => $this->shipment->reference_no,
            ]),
            'shipment_id' => $this->shipment->id,
            'driver_id' => $this->driver->id,
            'reference_no' => $this->shipment->reference_no,
            'url' => route('shipments.show', $this->shipment, absolute: true),
        ];
    }
}
