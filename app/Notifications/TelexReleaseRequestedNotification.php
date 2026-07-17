<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Shipment;
use App\Notifications\Traits\HasWhatsAppNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class TelexReleaseRequestedNotification extends Notification implements ShouldQueue
{
    use HasWhatsAppNotification, Queueable;

    public int $timeout = 60;

    public int $tries = 2;

    public function __construct(
        public readonly Shipment $shipment,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];
        $channels = $this->viaWithWhatsApp($channels, $notifiable);

        return $channels;
    }

    public function toWhatsApp(object $notifiable): array
    {
        return [
            'body' => "📢 *Telex Release Requested*\n\nShipper requested a Telex Release for shipment *{$this->shipment->reference_no}* (BL: {$this->shipment->bill_of_lading_number}).\n\nPlease check your email from Sallaum Lines / carrier and record the Telex Release text in the operations terminal or web dashboard.",
            'related_entity' => $this->shipment,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Telex Release Requested'),
            'body' => __('Shipper requested a Telex Release for shipment :ref (BL: :bl).', [
                'ref' => $this->shipment->reference_no,
                'bl' => $this->shipment->bill_of_lading_number ?: '—',
            ]),
            'shipment_id' => $this->shipment->id,
            'reference_no' => $this->shipment->reference_no,
            'url' => route('shipments.show', $this->shipment, absolute: true),
        ];
    }
}
