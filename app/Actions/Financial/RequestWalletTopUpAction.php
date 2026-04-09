<?php

declare(strict_types=1);

namespace App\Actions\Financial;

use App\Enums\WalletTopUpStatus;
use App\Models\Shipper;
use App\Models\WalletTopUp;
use Illuminate\Http\UploadedFile;

final class RequestWalletTopUpAction
{
    /**
     * Creates a pending WalletTopUp request with uploaded receipt.
     */
    public function execute(Shipper $shipper, float $amount, UploadedFile|string $receipt, ?string $reference = null): WalletTopUp
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Top-up amount must be greater than zero.');
        }

        $receiptPath = is_string($receipt)
            ? $receipt
            : $receipt->store('receipts', 'public');

        return $shipper->walletTopUps()->create([
            'wallet_id' => $shipper->wallet->id,
            'amount' => $amount,
            'receipt_path' => $receiptPath,
            'reference' => $reference,
            'status' => WalletTopUpStatus::Pending,
        ]);
    }
}
