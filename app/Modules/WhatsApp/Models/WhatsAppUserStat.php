<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Models;

use App\Models\Driver;
use App\Models\Shipper;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class WhatsAppUserStat extends Model
{
    protected $table = 'whatsapp_user_stats';

    protected $fillable = [
        'phone_number',
        'contact_id',
        'contact_type',
        'contact_name',
        'contact_role',
        'total_messages',
        'conversation_count',
        'first_contact_at',
        'last_contact_at',
    ];

    protected function casts(): array
    {
        return [
            'total_messages' => 'integer',
            'conversation_count' => 'integer',
            'first_contact_at' => 'datetime',
            'last_contact_at' => 'datetime',
        ];
    }

    public function contact(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Resolve a human-readable role label from the morph class.
     */
    public static function resolveRole(string $morphClass): string
    {
        return match ($morphClass) {
            Shipper::class => 'shipper',
            Staff::class => 'staff',
            Driver::class => 'driver',
            default => 'unknown',
        };
    }

    /**
     * Resolve the display name from the matched contact model.
     *
     * @param  Shipper|Staff|Driver|null  $contact
     */
    public static function resolveContactName(mixed $contact): ?string
    {
        if ($contact === null) {
            return null;
        }

        if ($contact instanceof Shipper) {
            return $contact->company_name ?? $contact->user?->name;
        }

        if ($contact instanceof Staff || $contact instanceof Driver) {
            return $contact->user?->name ?? $contact->phone;
        }

        return null;
    }
}
