<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Shipment;
use App\Models\SystemSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class WalletDebitNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Shipment $shipment,
        public readonly float $debitedAmount,
        public readonly float $currentBalance,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $shipperUserId = $this->shipment->shipper?->user_id;

        if ($shipperUserId !== null && (int) $notifiable->getKey() === (int) $shipperUserId) {
            return ['mail', 'database'];
        }

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
            ->mailer($setting->getMailerFor('accounts'))
            ->subject(__('Wallet Debit Notification').' — $'.number_format($this->debitedAmount, 2))
            ->markdown('emails.wallet-debit', [
                'notifiable' => $notifiable,
                'shipment' => $this->shipment,
                'debitedAmount' => $this->debitedAmount,
                'currentBalance' => $this->currentBalance,
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
            'title' => __('Wallet Debited'),
            'body' => __('Wallet debited $:amount for shipment :ref. New balance: $:balance.', [
                'amount' => number_format($this->debitedAmount, 2),
                'ref' => $this->shipment->reference_no,
                'balance' => number_format($this->currentBalance, 2),
            ]),
            'shipment_id' => $this->shipment->id,
            'reference_no' => $this->shipment->reference_no,
            'debited_amount' => $this->debitedAmount,
            'current_balance' => $this->currentBalance,
            'url' => route('shipper.wallet.index', absolute: true),
        ];
    }
}
