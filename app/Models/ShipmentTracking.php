<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ShipmentStatus;
use Database\Factories\ShipmentTrackingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ShipmentTracking extends Model
{
    /** @use HasFactory<ShipmentTrackingFactory> */
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'status',
        'workshop_id',
        'note',
        'metadata',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'metadata' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }
}
