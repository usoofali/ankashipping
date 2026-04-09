<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ChargeItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class ChargeItem extends Model
{
    /** @use HasFactory<ChargeItemFactory> */
    use HasFactory;

    protected $fillable = [
        'item',
        'description',
        'default_amount',
        'apply_customer_discount',
    ];

    protected function casts(): array
    {
        return [
            'default_amount' => 'decimal:2',
            'apply_customer_discount' => 'boolean',
        ];
    }
}
