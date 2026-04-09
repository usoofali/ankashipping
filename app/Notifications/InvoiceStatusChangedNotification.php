<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Shipment;
use Illuminate\Notifications\Notification;

final class InvoiceStatusChangedNotification extends Notification
{
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
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Invoice status updated'),
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
