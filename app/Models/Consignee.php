<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ConsigneeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Consignee extends Model
{
    /** @use HasFactory<ConsigneeFactory> */
    use HasFactory;

    protected $fillable = [
        'shipper_id',
        'name',
        'contact',
        'phone',
        'address',
        'country_id',
        'state_id',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function shipper(): BelongsTo
    {
        return $this->belongsTo(Shipper::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}
