<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Prealert;
use App\Models\SystemSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PrealertCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Prealert $prealert,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        // Shipper receives both mail and database notifications
        if ($notifiable->hasRole('shipper') && (int) $notifiable->shipper?->id === (int) $this->prealert->shipper_id) {
            return ['mail', 'database'];
        }

        // Staff/Admins only receive database notifications
        return ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $setting = SystemSetting::current()->loadMissing(['city', 'state']);
        $companyName = $setting->company_name ?: config('app.name');
        $cityName = $setting->city?->name;
        $stateName = $setting->state?->name;
        $location = collect([$cityName, $stateName])->filter()->implode(', ');
        $emailLogo = $setting->logoSrcForEmail();

        return (new MailMessage)
            ->mailer($setting->getMailerFor('operations'))
            ->subject(__('New Prealert Created – VIN :vin', ['vin' => $this->prealert->vin]))
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
            'title' => __('New Prealert Created'),
            'body' => __('A new prealert has been created for VIN :vin by :shipper.', [
                'vin' => $this->prealert->vin,
                'shipper' => $this->prealert->shipper?->company_name ?? $this->prealert->shipper?->user?->name,
            ]),
            'prealert_id' => $this->prealert->id,
            'vin' => $this->prealert->vin,
            'url' => route('prealerts.index', absolute: true), // Link to prealerts list or show if implemented
        ];
    }
}
