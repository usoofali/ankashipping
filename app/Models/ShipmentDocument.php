<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ShipmentDocumentType;
use Database\Factories\ShipmentDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ShipmentDocument extends Model
{
    /** @use HasFactory<ShipmentDocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'document_type',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => ShipmentDocumentType::class,
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ShipmentDocumentFile::class);
    }
}
