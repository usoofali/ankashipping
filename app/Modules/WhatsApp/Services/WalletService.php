<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Services;

use App\Actions\Financial\RequestWalletTopUpAction;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\TransactionType;
use App\Enums\WalletTopUpStatus;
use App\Models\Shipment;
use App\Models\Shipper;
use App\Models\User;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMenuState;
use App\Notifications\WalletTopUpRequestedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class WalletService
{
    public function __construct(
        protected WhatsAppService $waService,
        protected RequestWalletTopUpAction $topUpAction
    ) {}

    public function startWalletFlow(WhatsAppConversation $conversation): void
    {
        if ($conversation->contact_type !== Shipper::class) {
            $this->waService->sendMessage($conversation->phone_number, "⚠️ *Access Denied*\n\nThe Wallet feature is only available to registered shippers.");

            return;
        }

        $menu = "💰 *Wallet Management*\n";
        $menu .= "━━━━━━━━━━━━━━━━━━━\n";
        $menu .= "1️⃣ *Balance & Transactions*\n";
        $menu .= "2️⃣ *Fund / Top-up Wallet*\n\n";
        $menu .= "💡 _Type *'Menu'* to go back._";

        $this->waService->sendMessage($conversation->phone_number, $menu);

        $conversation->menuState()->updateOrCreate([], [
            'current_step' => 'wallet_menu_selection',
            'data_payload' => [],
        ]);
    }

    public function handleStep(WhatsAppConversation $conversation, WhatsAppMenuState $state, string $text, ?string $mediaId): void
    {
        $payload = $state->data_payload ?? [];
        $cleanText = trim($text);

        switch ($state->current_step) {
            case 'wallet_menu_selection':
                if ($cleanText === '1') {
                    $this->sendSummary($conversation);
                } elseif ($cleanText === '2') {
                    $state->update(['current_step' => 'wallet_fund_awaiting_amount']);
                    $this->waService->sendMessage($conversation->phone_number, "💵 *Fund Wallet*\n\nPlease enter the *Amount* you wish to top up (USD):");
                } else {
                    $this->waService->sendMessage($conversation->phone_number, "⚠️ Invalid selection. Please choose:\n\n1️⃣ Balance & Transactions\n2️⃣ Fund Wallet");
                }
                break;

            case 'wallet_fund_awaiting_amount':
                $amount = (float) $cleanText;
                if ($amount <= 0) {
                    $this->waService->sendMessage($conversation->phone_number, '⚠️ Invalid amount. Please enter a number greater than 0.');

                    return;
                }

                $payload['amount'] = $amount;
                $state->update([
                    'current_step' => 'wallet_fund_awaiting_reference',
                    'data_payload' => $payload,
                ]);

                $this->waService->sendMessage($conversation->phone_number, "✅ *Amount saved: \${$amount}*\n\nPlease enter a *Bank Reference* or Memo (or send *0* to skip):");
                break;

            case 'wallet_fund_awaiting_reference':
                $payload['reference'] = ($cleanText === '0') ? null : $text;
                $state->update([
                    'current_step' => 'wallet_fund_awaiting_receipt',
                    'data_payload' => $payload,
                ]);

                $this->waService->sendMessage($conversation->phone_number, "✅ *Reference saved.*\n\nFinally, please upload your *Transfer Receipt* (Image or PDF):");
                break;

            case 'wallet_fund_awaiting_receipt':
                if (! $mediaId) {
                    $this->waService->sendMessage($conversation->phone_number, '⚠️ Please upload a valid Receipt (Image or PDF) to continue.');

                    return;
                }

                $this->finalizeTopUp($conversation, $state, $mediaId);
                break;
        }
    }

    protected function finalizeTopUp(WhatsAppConversation $conversation, WhatsAppMenuState $state, string $mediaId): void
    {
        $payload = $state->data_payload;
        $this->waService->sendMessage($conversation->phone_number, '⏳ *Processing your request...*');

        // 1. Download Media
        $localPath = $this->waService->downloadMedia($mediaId);
        if (! $localPath || ! Storage::disk('local')->exists($localPath)) {
            $this->waService->sendMessage($conversation->phone_number, '❌ Failed to download receipt. Please try again.');

            return;
        }

        // 2. Move to Temporary Storage
        $extension = pathinfo($localPath, PATHINFO_EXTENSION);
        $tmpFilename = 'receipts/tmp/'.uniqid().'.'.$extension;
        Storage::disk('public')->put($tmpFilename, Storage::disk('local')->get($localPath));
        Storage::disk('local')->delete($localPath);

        /** @var Shipper $shipper */
        $shipper = Shipper::find($conversation->contact_id);

        try {
            // 3. Move from tmp to final receipts folder before execution
            $finalPath = str_replace('receipts/tmp/', 'receipts/', $tmpFilename);
            Storage::disk('public')->move($tmpFilename, $finalPath);

            // 4. Execute Action
            $topUp = $this->topUpAction->execute(
                shipper: $shipper,
                amount: (float) $payload['amount'],
                receipt: $finalPath,
                reference: $payload['reference'] ?? null
            );

            // 4. Notify Admins/Staff (Mirrors Wallet Controller logic)
            $adminRoleNames = Role::query()->where('name', '!=', 'shipper')->pluck('name');
            $recipientIds = User::query()
                ->role($adminRoleNames)
                ->pluck('id')
                ->merge(User::query()->whereHas('staff')->pluck('id'))
                ->merge(User::query()->whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->pluck('id'))
                ->unique()
                ->values();

            $recipients = User::query()->whereIn('id', $recipientIds)->get();
            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new WalletTopUpRequestedNotification($topUp));
            }

            // 5. Success Message
            $this->waService->sendMessage(
                $conversation->phone_number,
                "🎉 *Top-Up Request Submitted!*\n\n*Amount:* \${$payload['amount']}\n*Status:* PENDING\n\nOur team will verify your receipt and update your balance shortly."
            );

            $state->delete();
        } catch (\Exception $e) {
            $this->waService->sendMessage($conversation->phone_number, '❌ Error processing request: '.$e->getMessage());
        }
    }

    public function sendSummary(WhatsAppConversation $conversation): void
    {
        /** @var Shipper $shipper */
        $shipper = Shipper::with([
            'wallet.transactions' => fn ($q) => $q->latest()->limit(5),
            'wallet.walletTopUps' => fn ($q) => $q->where('status', WalletTopUpStatus::Pending)->latest()->limit(3),
            'shipments' => fn ($q) => $q->with('invoice')->whereHas('invoice'),
        ])->find($conversation->contact_id);

        if (! $shipper || ! $shipper->wallet) {
            $this->waService->sendMessage($conversation->phone_number, "⚠️ *No Wallet Found*\n\nPlease contact our team.");

            return;
        }

        $wallet = $shipper->wallet;
        $currency = strtoupper((string) config('financial.currency', 'USD'));

        $message = "💰 *Your Wallet Summary*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━\n";
        $message .= "*Current Balance:* {$currency} ".number_format((float) $wallet->balance, 2)."\n";

        $pendingTopUps = $wallet->walletTopUps;
        if ($pendingTopUps->isNotEmpty()) {
            $message .= "\n⏳ *Pending Top-ups:*\n";
            foreach ($pendingTopUps as $topUp) {
                $message .= "  • {$currency} ".number_format((float) $topUp->amount, 2)." _(awaiting approval)_\n";
            }
        }

        $unpaidInvoices = $shipper->shipments
            ->filter(fn (Shipment $s) => $s->invoice
                && $s->invoice->status === InvoiceStatus::Completed
                && $s->payment_status !== PaymentStatus::Paid
            )
            ->map(fn (Shipment $s) => ['invoice' => $s->invoice, 'ref' => $s->reference_no]);

        if ($unpaidInvoices->isNotEmpty()) {
            $message .= "\n🧾 *Outstanding Invoices:*\n";
            foreach ($unpaidInvoices->take(5) as $item) {
                $total = number_format((float) $item['invoice']->total_amount, 2);
                $due = $item['invoice']->due_at ? $item['invoice']->due_at->format('d M Y') : 'N/A';
                $message .= "  • *{$item['ref']}* — {$currency} {$total} _(due {$due})_\n";
            }
        }

        $transactions = $wallet->transactions;
        if ($transactions->isNotEmpty()) {
            $message .= "\n📋 *Last 5 Transactions:*\n";
            foreach ($transactions as $tx) {
                $icon = match ($tx->type) {
                    TransactionType::Credit => '🟢',
                    TransactionType::Debit => '🔴',
                    TransactionType::Adjustment => '🔵',
                };
                $sign = $tx->type === TransactionType::Credit ? '+' : '-';
                $amount = number_format((float) $tx->amount, 2);
                $date = $tx->created_at->format('d M');
                $desc = $tx->description ? Str::limit($tx->description, 25) : $tx->type->name;
                $message .= "  {$icon} {$sign}{$currency}{$amount} · {$desc} _{$date}_\n";
            }
        }

        $message .= "\n💡 _Type *'Menu'* to go back._";

        $this->waService->sendMessage($conversation->phone_number, $message);

        // Allow continuing in the flow if they want to fund
        $conversation->menuState()->update(['current_step' => 'wallet_menu_selection']);
    }
}
