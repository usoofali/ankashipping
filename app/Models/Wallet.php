<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WalletFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Wallet extends Model
{
    /** @use HasFactory<WalletFactory> */
    use HasFactory;

    protected $fillable = [
        'shipper_id',
        'balance',
    ];

    protected static function booted(): void
    {
        self::saving(function (Wallet $wallet): void {
            $wallet->currency = (string) config('financial.currency');
        });
    }

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
        ];
    }

    public function shipper(): BelongsTo
    {
        return $this->belongsTo(Shipper::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function walletTopUps(): HasMany
    {
        return $this->hasMany(WalletTopUp::class);
    }
}
