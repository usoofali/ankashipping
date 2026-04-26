<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\Shipment;
use App\Models\SystemSetting;
use App\Support\ShipmentPdfSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PaymentReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable, ShipmentPdfSupport;

    public int $timeout = 80;

    public int $tries = 2;

    public function __construct(
        public readonly Shipment $shipment,
        public readonly Invoice $invoice,
        public readonly float $paidAmount,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];
        $shipperUserId = $this->shipment->shipper?->user_id;

        if ($shipperUserId !== null && (int) $notifiable->getKey() === (int) $shipperUserId) {
            $channels[] = 'mail';
            $channels[] = \App\Modules\WhatsApp\Channels\WhatsAppChannel::class;
        }

        return $channels;
    }

    public function toWhatsApp(object $notifiable): array
    {
        $amount = number_format($this->paidAmount, 2);

        return [
            'body' => "💳 *Payment Received:* We have confirmed your payment of *\${$amount}* for shipment *{$this->shipment->reference_no}*. Thank you!",
            'related_entity' => $this->shipment,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        ini_set('memory_limit', '512M');
        $setting = SystemSetting::current()->loadMissing(['city', 'state']);
        $companyName = $setting->company_name ?: config('app.name');
        $cityName = $setting->city?->name;
        $stateName = $setting->state?->name;
        $location = collect([$cityName, $stateName])->filter()->implode(', ');
        $emailLogo = $setting->logoSrcForEmail();

        $mail = (new MailMessage)
            ->mailer($setting->getMailerFor('accounts'))
            ->subject(__('Payment Received').' — '.$this->shipment->reference_no)
            ->markdown('emails.payment-received', [
                'notifiable' => $notifiable,
                'shipment' => $this->shipment,
                'invoice' => $this->invoice,
                'paidAmount' => $this->paidAmount,
                'setting' => $setting,
                'companyName' => $companyName,
                'location' => $location,
                'emailLogo' => $emailLogo,
            ]);

        // Attach Invoice
        if ($this->invoice) {
            $invoicePdf = $this->generateInvoicePdf($this->shipment);
            $mail->attachData($invoicePdf->output(), 'Invoice_'.$this->shipment->reference_no.'.pdf', [
                'mime' => 'application/pdf',
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
            'title' => __('Payment Received'),
            'body' => __('Payment of $:amount for shipment :ref has been received.', [
                'amount' => number_format($this->paidAmount, 2),
                'ref' => $this->shipment->reference_no,
            ]),
            'shipment_id' => $this->shipment->id,
            'reference_no' => $this->shipment->reference_no,
            'invoice_id' => $this->invoice->id,
            'paid_amount' => $this->paidAmount,
            'url' => route('shipments.show', $this->shipment, absolute: true),
        ];
    }
}
