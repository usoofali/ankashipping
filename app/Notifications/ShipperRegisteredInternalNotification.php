<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Shipper;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class ShipperRegisteredInternalNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly User $registeredUser,
        private readonly Shipper $shipper,
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
            'title' => __('New shipper registration'),
            'body' => __(':company registered (:email).', [
                'company' => $this->shipper->company_name,
                'email' => $this->registeredUser->email,
            ]),
            'shipper_id' => $this->shipper->id,
            'user_id' => $this->registeredUser->id,
            'company_name' => $this->shipper->company_name,
            'url' => route('shippers.show', $this->shipper, absolute: true),
        ];
    }
}
