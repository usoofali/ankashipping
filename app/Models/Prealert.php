<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PrealertStatus;
use App\Enums\ShippingMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Prealert extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipper_id',
        'consignee_id',
        'carrier_id',
        'destination_port_id',
        'shipping_mode',
        'shipment_id',
        'notes',
        'status',
        'notify_party_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => PrealertStatus::class,
            'shipping_mode' => ShippingMode::class,
        ];
    }

    public function shipper(): BelongsTo
    {
        return $this->belongsTo(Shipper::class);
    }

    public function consignee(): BelongsTo
    {
        return $this->belongsTo(Consignee::class);
    }

    public function notifyParty(): BelongsTo
    {
        return $this->belongsTo(Consignee::class, 'notify_party_id');
    }

    /**
     * @return HasMany<Vehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /**
     * Legacy/Helper for RoRo prealerts which have exactly one vehicle.
     */
    public function vehicle(): ?Vehicle
    {
        return $this->vehicles->first();
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    public function destinationPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'destination_port_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
