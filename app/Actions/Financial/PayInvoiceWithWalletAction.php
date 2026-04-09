<?php

declare(strict_types=1);

namespace App\Actions\Financial;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\InsufficientFundsException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Wallet;
use Exception;
use Illuminate\Support\Facades\DB;
use Throwable;

final class PayInvoiceWithWalletAction
{
    /**
     * Pay an invoice securely using wallet funds.
     * Partial payments are disallowed.
     *
     * @throws InsufficientFundsException|Exception|Throwable
     */
    public function execute(Wallet $wallet, Invoice $invoice, ?string $description = null): Payment
    {
        return DB::transaction(function () use ($wallet, $invoice, $description) {
            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();
            $invoice = Invoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

            if ($invoice->status === InvoiceStatus::Paid) {
                throw new Exception('Invoice is already paid.');
            }

            $amountDue = (float) $invoice->total_amount;

            if ((float) $wallet->balance < $amountDue) {
                throw new InsufficientFundsException('Insufficient wallet balance. Please fund your wallet to continue.');
            }

            // Deduct
            $wallet->balance -= $amountDue;
            $wallet->save();

            // Record transaction
            $transaction = $wallet->transactions()->create([
                'type' => 'debit',
                'amount' => $amountDue,
                'balance_after' => $wallet->balance,
                'description' => $description ?? "Payment for Invoice #{$invoice->invoice_number}",
            ]);

            // Ensure PaymentMethod "Wallet" exists
            $paymentMethod = PaymentMethod::firstOrCreate(
                ['slug' => 'wallet'],
                ['name' => 'Wallet', 'is_active' => true]
            );

            // Create Payment
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'payment_method_id' => $paymentMethod->id,
                'amount' => $amountDue,
                'status' => PaymentStatus::Completed,
                'transaction_id' => $transaction->id,
                'paid_at' => now(),
            ]);

            // Mark invoice as paid
            $invoice->status = InvoiceStatus::Paid;
            $invoice->save();

            return $payment;
        });
    }
}
