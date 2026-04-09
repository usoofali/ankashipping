<?php

declare(strict_types=1);

namespace App\Actions\Financial;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final class FundWalletAction
{
    /**
     * Fund a Shipper's wallet.
     *
     * @throws InvalidArgumentException|Throwable
     */
    public function execute(Wallet $wallet, float $amount, ?string $description = 'Wallet Funding', ?string $reference = null): Transaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Funding amount must be greater than zero.');
        }

        return DB::transaction(function () use ($wallet, $amount, $description, $reference) {
            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            $wallet->balance += $amount;
            $wallet->save();

            return $wallet->transactions()->create([
                'type' => 'credit',
                'amount' => $amount,
                'balance_after' => $wallet->balance,
                'description' => $description,
                'reference' => $reference,
            ]);
        });
    }
}
