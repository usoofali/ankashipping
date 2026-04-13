<?php

namespace App\Models;

use App\Enums\VehicleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleTracking extends Model
{
    protected $fillable = [
        'vehicle_id',
        'status',
        'workshop_id',
        'note',
        'metadata',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => VehicleStatus::class,
            'metadata' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }
}
