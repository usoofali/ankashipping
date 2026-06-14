<?php

declare(strict_types=1);

namespace App\Notifications\Traits;

use App\Modules\WhatsApp\Channels\WhatsAppChannel;

trait HasWhatsAppNotification
{
    /**
     * Determine if the notification should be sent via WhatsApp.
     */
    protected function shouldNotifyViaWhatsApp(object $notifiable, ?int $targetShipperId = null): bool
    {
        $shipper = $notifiable->shipper ?? null;

        if (! $shipper) {
            return false;
        }

        if ($targetShipperId !== null) {
            return (int) $shipper->id === (int) $targetShipperId;
        }

        return true;
    }

    /**
     * Add WhatsApp channel to the array if applicable.
     */
    protected function viaWithWhatsApp(array $channels, object $notifiable, ?int $targetShipperId = null): array
    {
        if ($this->shouldNotifyViaWhatsApp($notifiable, $targetShipperId)) {
            $channels[] = WhatsAppChannel::class;
        }

        return $channels;
    }
}
