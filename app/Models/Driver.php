<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DriverFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

final class Driver extends Model
{
    /** @use HasFactory<DriverFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'phone', // unique
        'email',
        'company',
    ];

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function shipments()
    {
        return $this->hasManyThrough(Shipment::class, Vehicle::class, 'driver_id', 'id', 'id', 'shipment_id');
    }
}
