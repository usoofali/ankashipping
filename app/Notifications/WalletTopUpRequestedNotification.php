<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\WalletTopUp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class WalletTopUpRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly WalletTopUp $topUp,
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
            'title' => __('New Wallet Top-Up Request'),
            'body' => __('A new funding request of $:amount has been submitted by :shipper.', [
                'amount' => number_format((float) $this->topUp->amount, 2),
                'shipper' => $this->topUp->shipper?->company_name ?? $this->topUp->shipper?->user?->name,
            ]),
            'top_up_id' => $this->topUp->id,
            'amount' => $this->topUp->amount,
            'url' => route('financials.top-ups.index', absolute: true),
        ];
    }
}
