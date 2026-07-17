<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Shipment;
use App\Models\SystemSetting;
use App\Notifications\Traits\HasWhatsAppNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TelexReleaseSubmittedNotification extends Notification implements ShouldQueue
{
    use HasWhatsAppNotification, Queueable;

    public int $timeout = 80;

    public int $tries = 2;

    public function __construct(
        public readonly Shipment $shipment,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'mail'];
        $channels = $this->viaWithWhatsApp($channels, $notifiable, (int) $this->shipment->shipper_id);

        return $channels;
    }

    public function toWhatsApp(object $notifiable): array
    {
        $text = $this->shipment->telex_release_text ?: '—';

        return [
            'body' => "📄 *TELEX RELEASE FOR SHIPMENT {$this->shipment->reference_no}* 📄\n*BL Number:* {$this->shipment->bill_of_lading_number}\n\n*Official Carrier Release Details:*\n━━━━━━━━━━━━━━━━━━━\n{$text}\n━━━━━━━━━━━━━━━━━━━\n\n✅ Your cargo is released and ready for pickup at the destination port against proper identification!",
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

        return (new MailMessage)
            ->mailer($setting->getMailerFor('operations'))
            ->subject(__('Official Telex Release Notice').' — '.$this->shipment->reference_no)
            ->markdown('emails.telex-release-submitted', [
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
            'title' => __('Official Telex Release Available'),
            'body' => __('Official Telex Release notice is now available for shipment :ref.', [
                'ref' => $this->shipment->reference_no,
            ]),
            'shipment_id' => $this->shipment->id,
            'reference_no' => $this->shipment->reference_no,
            'url' => route('shipments.show', $this->shipment, absolute: true),
        ];
    }
}
