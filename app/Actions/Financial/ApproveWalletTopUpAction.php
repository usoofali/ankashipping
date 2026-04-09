<?php

declare(strict_types=1);

namespace App\Actions\Financial;

use App\Enums\WalletTopUpStatus;
use App\Models\User;
use App\Models\WalletTopUp;
use Exception;
use Illuminate\Support\Facades\DB;

final class ApproveWalletTopUpAction
{
    public function __construct(private readonly FundWalletAction $fundWalletAction) {}

    public function execute(User $approver, WalletTopUp $topUp): WalletTopUp
    {
        return DB::transaction(function () use ($approver, $topUp) {
            $topUp = WalletTopUp::where('id', $topUp->id)->lockForUpdate()->firstOrFail();

            if ($topUp->status !== WalletTopUpStatus::Pending) {
                throw new Exception('Only pending top-ups can be approved.');
            }

            // Fund the wallet officially
            $this->fundWalletAction->execute(
                $topUp->wallet,
                (float) $topUp->amount,
                "Wallet funding approved by {$approver->name}",
                $topUp->reference
            );

            $topUp->status = WalletTopUpStatus::Approved;
            $topUp->approved_by = $approver->id;
            $topUp->save();

            return $topUp;
        });
    }
}
