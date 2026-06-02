<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PortFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Port extends Model
{
    /** @use HasFactory<PortFactory> */
    use HasFactory;

    protected $fillable = [
        'country_id',
        'state_id',
        'warehouse_id',
        'name',
        'type',
        'terminal_name',
        'terminal_state',
        'terminal_zipcode',
        'terminal_address',
        'terminal_phone',
        'terminal_email',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function originShipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'origin_port_id');
    }

    public function destinationShipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'destination_port_id');
    }
}
