<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VehicleDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class VehicleDocument extends Model
{
    protected $fillable = [
        'vehicle_id',
        'document_type',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => VehicleDocumentType::class,
        ];
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return HasMany<VehicleDocumentFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(VehicleDocumentFile::class);
    }
}
