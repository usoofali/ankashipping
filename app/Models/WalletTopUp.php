<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WalletTopUpStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WalletTopUp extends Model
{
    protected $fillable = [
        'wallet_id',
        'shipper_id',
        'amount',
        'receipt_path',
        'status',
        'reference',
        'approved_by',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => WalletTopUpStatus::class,
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function shipper(): BelongsTo
    {
        return $this->belongsTo(Shipper::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
