<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PrealertStatus;
use Database\Factories\PrealertFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Prealert extends Model
{
    /** @use HasFactory<PrealertFactory> */
    use HasFactory;

    protected $fillable = [
        'shipper_id',
        'consignee_id',
        'vehicle_id',
        'carrier_id',
        'destination_port_id',
        'vin',
        'gatepass_pin',
        'auction_receipt',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => PrealertStatus::class,
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

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
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
}
