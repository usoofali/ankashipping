<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class VehicleDocumentAttachedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Vehicle $vehicle,
        public readonly VehicleDocument $vehicleDocument,
        public readonly string $documentLabel = 'Document',
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
            'title' => __(':label Attached', ['label' => $this->documentLabel]),
            'body' => __('A new :label has been attached to vehicle :vin.', [
                'label' => strtolower($this->documentLabel),
                'vin' => $this->vehicle->vin,
            ]),
            'shipment_id' => $this->vehicle->shipment_id,
            'vehicle_id' => $this->vehicle->id,
            'reference_no' => $this->vehicle->shipment?->reference_no,
            'url' => route('shipments.show', $this->vehicle->shipment_id, absolute: true),
        ];
    }
}
