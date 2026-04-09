<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CarrierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Carrier extends Model
{
    /** @use HasFactory<CarrierFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}
