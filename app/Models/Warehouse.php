<?php

namespace App\Models;

use Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

final class Warehouse extends Model
{
    /** @use HasFactory<WarehouseFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
    ];

    /**
     * @return HasMany<Port, $this>
     */
    public function ports(): HasMany
    {
        return $this->hasMany(Port::class);
    }
}
