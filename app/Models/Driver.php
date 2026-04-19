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
        'phone',
        'email',
        'company',
    ];

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}
