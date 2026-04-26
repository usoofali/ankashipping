<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\SystemSetting;
use App\Models\WalletTopUp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class WalletTopUpApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $timeout = 80;

    public int $tries = 2;

    public function __construct(
        private readonly WalletTopUp $topUp,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];
        $shipperUserId = $this->topUp->shipper?->user_id;

        if ($shipperUserId !== null && (int) $notifiable->getKey() === (int) $shipperUserId) {
            $channels[] = 'mail';
            $channels[] = \App\Modules\WhatsApp\Channels\WhatsAppChannel::class;
        }

        return $channels;
    }

    public function toWhatsApp(object $notifiable): array
    {
        $amount = number_format((float) $this->topUp->amount, 2);
        $balance = number_format((float) ($this->topUp->shipper->wallet_balance ?? 0), 2);

        return [
            'body' => "✅ *Top-up Approved:* \${$amount} has been added to your wallet.\n\nNew Balance: *\${$balance}*",
            'related_entity' => $this->topUp,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        ini_set('memory_limit', '512M');
        $this->topUp->refresh();

        $setting = SystemSetting::current()->loadMissing(['city', 'state']);
        $companyName = $setting->company_name ?: config('app.name');
        $cityName = $setting->city?->name;
        $stateName = $setting->state?->name;
        $location = collect([$cityName, $stateName])->filter()->implode(', ');
        $emailLogo = $setting->logoSrcForEmail();

        return (new MailMessage)
            ->mailer($setting->getMailerFor('accounts'))
            ->subject(__('Wallet Top-Up Approved').' — $'.number_format((float) $this->topUp->amount, 2))
            ->markdown('emails.wallet-top-up-approved', [
                'notifiable' => $notifiable,
                'topUp' => $this->topUp,
                'setting' => $setting,
                'companyName' => $companyName,
                'location' => $location,
                'emailLogo' => $emailLogo,
                'url' => route('shipper.wallet.index', absolute: true),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Wallet Top-Up Approved'),
            'body' => __('Your funding request of $:amount has been approved.', [
                'amount' => number_format((float) $this->topUp->amount, 2),
            ]),
            'top_up_id' => $this->topUp->id,
            'amount' => $this->topUp->amount,
            'url' => route('shipper.wallet.index', absolute: true),
        ];
    }
}
