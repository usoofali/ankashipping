<?php

declare(strict_types=1);

namespace App\Actions\Financial;

use App\Enums\WalletTopUpStatus;
use App\Models\User;
use App\Models\WalletTopUp;
use Exception;

final class RejectWalletTopUpAction
{
    public function execute(User $approver, WalletTopUp $topUp, string $reason): WalletTopUp
    {
        if ($topUp->status !== WalletTopUpStatus::Pending) {
            throw new Exception('Only pending top-ups can be rejected.');
        }

        $topUp->status = WalletTopUpStatus::Rejected;
        $topUp->approved_by = $approver->id;
        $topUp->rejection_reason = $reason;
        $topUp->save();

        return $topUp;
    }
}
