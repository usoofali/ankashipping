<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Enums\TransactionType;
use App\Models\ActivityLog;
use App\Models\Shipment;
use App\Models\ShipmentTracking;
use App\Models\User;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\WalletDebitNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait HandlesShipmentPayments
{
    /**
     * Process a wallet payment for a shipment.
     */
    public function processShipmentPayment(Shipment $shipment): bool
    {
        $this->authorize('shipments.pay');

        $shipper = $shipment->shipper;
        $invoice = $shipment->invoice;

        if (! $shipper || ! $invoice) {
            $this->notification()->error(__('Payment Error'), __('Missing shipper or invoice data.'));

            return false;
        }

        $wallet = $shipper->wallet;
        if (! $wallet) {
            $this->notification()->error(__('Payment Error'), __('Shipper does not have a wallet.'));

            return false;
        }

        $amountToPay = (float) $invoice->total_amount;
        $currentBalance = (float) ($wallet->balance ?? 0);

        if ($currentBalance < $amountToPay) {
            $this->notification()->error(__('Insufficient Balance'), __('You need $:need more in your wallet.', ['need' => number_format($amountToPay - $currentBalance, 2)]));

            return false;
        }

        try {
            DB::transaction(function () use ($shipper, $invoice, $wallet, $amountToPay, $shipment): void {
                $wallet->refresh();
                if ((float) $wallet->balance < $amountToPay) {
                    throw new \Exception('Insufficient balance at transaction time.');
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
                    'user_id' => Auth::id(),
                    'action' => 'payment_via_wallet',
                    'properties' => [
                        'amount' => $amountToPay,
                        'reference_no' => $shipment->reference_no,
                        'payer_id' => Auth::id(),
                        'payer_name' => Auth::user()?->name,
                    ],
                ]);

                // 5. Add Tracking
                ShipmentTracking::query()->create([
                    'shipment_id' => $shipment->id,
                    'status' => ShipmentStatus::Completed,
                    'note' => __('Paid via wallet by :user. Shipment completed.', ['user' => Auth::user()?->name]),
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
            });

            $this->notification()->success(__('Payment Successful'), __('Payment for :ref has been processed.', ['ref' => $shipment->reference_no]));

            return true;
        } catch (\Throwable $e) {
            $this->notification()->error(__('Payment Failed'), $e->getMessage());

            return false;
        }
    }
}
