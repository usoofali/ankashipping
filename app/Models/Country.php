<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CountryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Country extends Model
{
    /** @use HasFactory<CountryFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'iso2',
        'iso3',
    ];

    public function states(): HasMany
    {
        return $this->hasMany(State::class);
    }

    public function ports(): HasMany
    {
        return $this->hasMany(Port::class);
    }

    public function shippers(): HasMany
    {
        return $this->hasMany(Shipper::class);
    }
}
