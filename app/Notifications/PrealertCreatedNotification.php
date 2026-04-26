<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\ShippingMode;
use App\Models\Prealert;
use App\Models\SystemSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PrealertCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @var int Maximum seconds this job may run before timing out. */
    public int $timeout = 80;

    /** @var int Number of times to attempt the job. */
    public int $tries = 2;

    public function __construct(
        private readonly Prealert $prealert,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        // Shipper receives both mail and database notifications
        if ($notifiable->shipper && (int) $notifiable->shipper->id === (int) $this->prealert->shipper_id) {
            return ['mail', 'database'];
        }

        // Staff/Admins only receive database notifications
        return ['database'];
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
            ->subject($this->prealert->shipment_id
                ? __('New Prealert for Container :ref – :count Vehicles', ['ref' => $this->prealert->shipment?->reference_no ?? __('Existing'), 'count' => $this->prealert->vehicles->count()])
                : ($this->prealert->shipping_mode === ShippingMode::Container
                    ? __('New Container Prealert Created – :count Vehicles', ['count' => $this->prealert->vehicles->count()])
                    : __('New RoRo Prealert Created – VIN :vin', ['vin' => $this->prealert->vehicles->first()?->vin])))
            ->markdown('emails.prealert-created', [
                'notifiable' => $notifiable,
                'prealert' => $this->prealert,
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
            'title' => $this->prealert->shipment_id ? __('Vehicles Targeting Container') : ($this->prealert->shipping_mode === ShippingMode::Container ? __('New Container Prealert') : __('New RoRo Prealert')),
            'body' => $this->prealert->shipment_id
                ? __('A new prealert for :count vehicles targeting container :ref has been created by :shipper.', [
                    'count' => $this->prealert->vehicles->count(),
                    'ref' => $this->prealert->shipment?->reference_no ?? __('Existing'),
                    'shipper' => $this->prealert->shipper?->company_name ?? $this->prealert->shipper?->user?->name,
                ])
                : ($this->prealert->shipping_mode === ShippingMode::Container
                    ? __('A new Container prealert has been created for :count vehicles by :shipper.', [
                        'count' => $this->prealert->vehicles->count(),
                        'shipper' => $this->prealert->shipper?->company_name ?? $this->prealert->shipper?->user?->name,
                    ])
                    : __('A new RoRo prealert has been created for VIN :vin by :shipper.', [
                        'vin' => $this->prealert->vehicles->first()?->vin,
                        'shipper' => $this->prealert->shipper?->company_name ?? $this->prealert->shipper?->user?->name,
                    ])),
            'prealert_id' => $this->prealert->id,
            'vin' => $this->prealert->shipping_mode === ShippingMode::Container ? null : $this->prealert->vehicles->first()?->vin,
            'url' => route('prealerts.index', absolute: true), // Link to prealerts list or show if implemented
        ];
    }
}
