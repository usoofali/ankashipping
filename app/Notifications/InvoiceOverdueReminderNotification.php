<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\Shipment;
use App\Models\SystemSetting;
use App\Modules\WhatsApp\Services\WhatsAppDocumentService;
use App\Notifications\Traits\HasWhatsAppNotification;
use App\Support\ShipmentPdfSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

final class InvoiceOverdueReminderNotification extends Notification implements ShouldQueue
{
    use HasWhatsAppNotification, Queueable, ShipmentPdfSupport;

    public int $timeout = 80;

    public int $tries = 2;

    public function __construct(
        public readonly Shipment $shipment,
        public readonly Invoice $invoice,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        $isShipper = (int) $notifiable->getKey() === (int) $this->shipment->shipper?->user_id
            || (method_exists($notifiable, 'hasRole') && $notifiable->hasRole('shipper'));

        if ($isShipper) {
            $channels[] = 'mail';
            $channels = $this->viaWithWhatsApp($channels, $notifiable, (int) $this->shipment->shipper_id);
        }

        return $channels;
    }

    public function toWhatsApp(object $notifiable): array
    {
        try {
            $files = [];

            try {
                $docService = app(WhatsAppDocumentService::class);
                $files[] = $docService->getInvoicePayload($this->shipment);
            } catch (\Throwable $e) {
                Log::warning("InvoiceOverdueReminderNotification: Could not generate WhatsApp invoice attachment for #{$this->invoice->invoice_number}: {$e->getMessage()}");
            }

            $amount = number_format((float) $this->invoice->total_amount, 2);
            $url = route('shipments.show', $this->shipment, absolute: true);

            return [
                'body' => "💳 *Payment Reminder: Invoice Overdue*\n\n"
                    ."Your invoice *#{$this->invoice->invoice_number}* for shipment *{$this->shipment->reference_no}* is overdue for payment.\n"
                    ."*Outstanding Balance:* \${$amount}\n\n"
                    ."Please view invoice and settle payment: {$url}",
                'files' => array_filter($files),
                'related_entity' => $this->shipment,
            ];
        } catch (\Throwable $e) {
            Log::error("InvoiceOverdueReminderNotification: Error building WhatsApp message for invoice #{$this->invoice->invoice_number}: {$e->getMessage()}", [
                'invoice_id' => $this->invoice->id,
                'shipment_id' => $this->shipment->id,
                'exception' => $e,
            ]);

            return [];
        }
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

        $subject = __('Payment Reminder: Invoice Overdue')." — [#{$this->invoice->invoice_number}] — {$this->shipment->reference_no}";

        $mail = (new MailMessage)
            ->mailer($setting->getMailerFor('accounts'))
            ->subject($subject)
            ->markdown('emails.invoice-overdue-reminder', [
                'notifiable' => $notifiable,
                'shipment' => $this->shipment,
                'invoice' => $this->invoice,
                'setting' => $setting,
                'companyName' => $companyName,
                'location' => $location,
                'emailLogo' => $emailLogo,
            ]);

        // Attach invoice PDF
        try {
            $invoicePdf = $this->generateInvoicePdf($this->shipment);
            $mail->attachData($invoicePdf->output(), 'Invoice_'.$this->shipment->reference_no.'.pdf', [
                'mime' => 'application/pdf',
            ]);
        } catch (\Throwable $e) {
            Log::error("InvoiceOverdueReminderNotification: Failed to generate Invoice PDF for invoice #{$this->invoice->invoice_number} (Shipment: {$this->shipment->reference_no}): {$e->getMessage()}", [
                'invoice_id' => $this->invoice->id,
                'shipment_id' => $this->shipment->id,
                'exception' => $e,
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
            'title' => __('Payment Reminder: Invoice Overdue'),
            'body' => __('Invoice #:num for shipment :ref is overdue. Total balance: $:amount.', [
                'num' => $this->invoice->invoice_number,
                'ref' => $this->shipment->reference_no,
                'amount' => number_format((float) $this->invoice->total_amount, 2),
            ]),
            'shipment_id' => $this->shipment->id,
            'reference_no' => $this->shipment->reference_no,
            'invoice_id' => $this->invoice->id,
            'total_amount' => $this->invoice->total_amount,
            'url' => route('shipments.show', $this->shipment, absolute: true),
        ];
    }
}
