<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Driver;
use App\Models\Shipment;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\ShipmentPdfSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

final class LogisticsBookingNotification extends Notification implements ShouldQueue
{
    use Queueable, ShipmentPdfSupport;

    public function __construct(
        public readonly Shipment $shipment,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        // Shipper
        if ($notifiable instanceof User && (int) $notifiable->getKey() === (int) $this->shipment->shipper?->user_id) {
            $channels[] = 'mail';
        }

        // Any notified Driver gets Mail
        if ($notifiable instanceof Driver) {
            $channels[] = 'mail';
        }

        // Warehouse get Mail
        if ($notifiable instanceof Warehouse) {
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
            ->subject(__('Logistics Booking Update').' — '.$this->shipment->reference_no);

        if ($notifiable instanceof Driver) {
            $signedUrl = URL::temporarySignedRoute(
                'shipments.dock-receipt.download.signed',
                now()->addDays(7),
                ['shipment' => $this->shipment->id]
            );

            return $mail->markdown('emails.driver-logistics-booking', [
                'notifiable' => $notifiable,
                'shipment' => $this->shipment,
                'signedUrl' => $signedUrl,
                'setting' => $setting,
                'companyName' => $companyName,
                'location' => $location,
                'emailLogo' => $emailLogo,
            ]);
        }

        $pdf = $this->generateDockReceiptPdf($this->shipment);

        return $mail->markdown('emails.logistics-booking', [
            'notifiable' => $notifiable,
            'shipment' => $this->shipment,
            'setting' => $setting,
            'companyName' => $companyName,
            'location' => $location,
            'emailLogo' => $emailLogo,
        ])->attachData($pdf->output(), 'DockReceipt_'.$this->shipment->reference_no.'.pdf', [
            'mime' => 'application/pdf',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Logistics Updated'),
            'body' => __('Logistics booking information for :ref has been updated.', [
                'ref' => $this->shipment->reference_no,
            ]),
            'shipment_id' => $this->shipment->id,
            'reference_no' => $this->shipment->reference_no,
            'url' => route('shipments.show', $this->shipment, absolute: true),
        ];
    }
}
