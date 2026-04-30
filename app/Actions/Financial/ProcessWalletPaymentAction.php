<?php

declare(strict_types=1);

namespace App\Actions\Financial;

use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Enums\TransactionType;
use App\Models\ActivityLog;
use App\Models\Shipment;
use App\Models\ShipmentTracking;
use App\Models\Shipper;
use App\Models\User;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\WalletDebitNotification;
use Exception;
use Illuminate\Support\Facades\DB;

class ProcessWalletPaymentAction
{
    /**
     * Process a wallet payment for a shipment.
     *
     * @param  User|null  $actor  The user performing the action. Can be null for system/bot actions.
     *
     * @throws Exception
     */
    public function execute(Shipment $shipment, Shipper $shipper, ?User $actor = null): bool
    {
        $invoice = $shipment->invoice;

        if (! $invoice) {
            throw new Exception('Missing invoice data.');
        }

        $wallet = $shipper->wallet;
        if (! $wallet) {
            throw new Exception('Shipper does not have a wallet.');
        }

        $amountToPay = (float) $invoice->total_amount;
        $currentBalance = (float) ($wallet->balance ?? 0);

        if ($currentBalance < $amountToPay) {
            throw new Exception('Insufficient balance. You need $'.number_format($amountToPay - $currentBalance, 2).' more in your wallet.');
        }

        return DB::transaction(function () use ($shipper, $invoice, $wallet, $amountToPay, $shipment, $actor): bool {
            $wallet->refresh();
            if ((float) $wallet->balance < $amountToPay) {
                throw new Exception('Insufficient balance at transaction time.');
            }

            // 1. Deduct funds
            $wallet->decrement('balance', $amountToPay);
            $newBalance = (float) $wallet->fresh()->balance;

            // 2. Create Transaction record
            $transaction = $wallet->transactions()->create([
                'type' => TransactionType::Debit,
                'amount' => $amountToPay,
                'balance_after' => $newBalance,
                'description' => __('Payment for shipment :ref', ['ref' => $shipment->reference_no]),
                'reference' => $shipment->reference_no,
            ]);

            // 3. Update Shipment & Payment Status
            $shipment->update([
                'payment_status' => PaymentStatus::Paid,
                'shipment_status' => ShipmentStatus::Completed,
            ]);

            // 4. Record Activity
            ActivityLog::query()->create([
                'shipment_id' => $shipment->id,
                'user_id' => $actor?->id,
                'action' => 'payment_via_wallet',
                'properties' => [
                    'amount' => $amountToPay,
                    'reference_no' => $shipment->reference_no,
                    'payer_id' => $actor?->id,
                    'payer_name' => $actor?->name ?? 'System/Bot',
                ],
            ]);

            // 5. Add Tracking
            $payerName = $actor?->name ?? 'the Shipper (via WhatsApp)';
            ShipmentTracking::query()->create([
                'shipment_id' => $shipment->id,
                'status' => ShipmentStatus::Completed,
                'note' => __('Paid via wallet by :user. Shipment completed.', ['user' => $payerName]),
                'recorded_at' => now(),
            ]);

            // 6. Send Notifications
            $shipperUser = $shipper->user;
            $staffUsers = User::permission('shipments.pay')->get();
            $allToNotify = collect([$shipperUser])->concat($staffUsers)->filter()->unique('id');

            foreach ($allToNotify as $user) {
                $user->notify(new PaymentReceivedNotification($shipment, $invoice, $amountToPay));
                $user->notify(new WalletDebitNotification($shipment, $amountToPay, $newBalance));
            }

            return true;
        });
    }
}
