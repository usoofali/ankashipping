<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Shipment;
use App\Models\SystemSetting;
use App\Notifications\Traits\HasWhatsAppNotification;
use App\Support\ShipmentPdfSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

final class BookedWithoutTitleReminderNotification extends Notification implements ShouldQueue
{
    use HasWhatsAppNotification, Queueable, ShipmentPdfSupport;

    public int $timeout = 80;

    public int $tries = 2;

    public function __construct(
        public readonly Shipment $shipment,
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
            $vehicle = $this->shipment->vehicles->first();
            $vehicleDesc = $vehicle
                ? trim("{$vehicle->year} {$vehicle->make} {$vehicle->model} (VIN: {$vehicle->vin})")
                : "Ref: {$this->shipment->reference_no}";

            $originPort = $this->shipment->originPort;
            $terminalLines = collect([
                $originPort?->terminal_name ?? $originPort?->name,
                $originPort?->terminal_address,
                collect([$originPort?->terminal_state, $originPort?->terminal_zipcode])->filter()->implode(' '),
                $originPort?->terminal_phone ? "Phone: {$originPort->terminal_phone}" : null,
            ])->filter()->implode("\n");

            $url = route('shipments.show', $this->shipment, absolute: true);

            $body = "📄 *Title Document Required — Ref: {$this->shipment->reference_no}*\n\n"
                ."Please be advised this unit (*{$vehicleDesc}*) was delivered without a title.\n\n"
                ."1️⃣ *Mail Original Title via FedEx:*\n"
                .($terminalLines !== '' ? "📍 *Delivery Address:*\n{$terminalLines}\n\n" : '')
                ."2️⃣ *Send Tracking & Copy:*\n"
                ."Please send us the FedEx tracking number and a photo/copy of the title by replying directly here on WhatsApp or replying to our email.\n\n"
                ."View shipment details: {$url}";

            return [
                'body' => $body,
                'related_entity' => $this->shipment,
            ];
        } catch (\Throwable $e) {
            Log::error("BookedWithoutTitleReminderNotification: Error building WhatsApp message for shipment {$this->shipment->reference_no}: {$e->getMessage()}", [
                'shipment_id' => $this->shipment->id,
                'reference_no' => $this->shipment->reference_no,
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

        $vehicle = $this->shipment->vehicles->first();
        $vehicleDesc = $vehicle
            ? trim("{$vehicle->year} {$vehicle->make} {$vehicle->model}")
            : $this->shipment->reference_no;

        $subject = __('Title Document Required')." — [Ref: {$this->shipment->reference_no}] — {$vehicleDesc}";

        $mail = (new MailMessage)
            ->mailer($setting->getMailerFor('operations'))
            ->subject($subject)
            ->markdown('emails.booked-without-title-reminder', [
                'notifiable' => $notifiable,
                'shipment' => $this->shipment,
                'vehicle' => $vehicle,
                'originPort' => $this->shipment->originPort,
                'setting' => $setting,
                'companyName' => $companyName,
                'location' => $location,
                'emailLogo' => $emailLogo,
            ]);

        if (! empty($setting->email)) {
            $mail->replyTo($setting->email, $companyName);
        }

        // Attach Dock Receipt PDF for vehicle & port reference
        try {
            $pdf = $this->generateDockReceiptPdf($this->shipment);
            $mail->attachData($pdf->output(), 'DockReceipt_'.$this->shipment->reference_no.'.pdf', [
                'mime' => 'application/pdf',
            ]);
        } catch (\Throwable $e) {
            Log::error("BookedWithoutTitleReminderNotification: Failed generating Dock Receipt PDF for shipment {$this->shipment->reference_no}: {$e->getMessage()}", [
                'shipment_id' => $this->shipment->id,
                'reference_no' => $this->shipment->reference_no,
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
        $vehicle = $this->shipment->vehicles->first();
        $vehicleDesc = $vehicle
            ? trim("{$vehicle->year} {$vehicle->make} {$vehicle->model} (VIN: {$vehicle->vin})")
            : $this->shipment->reference_no;

        return [
            'title' => __('Title Document Required'),
            'body' => __('Please mail the title for :vehicle via FedEx to the terminal address. Send tracking & copy by replying to our email or WhatsApp.', [
                'vehicle' => $vehicleDesc,
            ]),
            'shipment_id' => $this->shipment->id,
            'reference_no' => $this->shipment->reference_no,
            'vin' => $vehicle?->vin,
            'url' => route('shipments.show', $this->shipment, absolute: true),
        ];
    }
}
