<?php

declare(strict_types=1);

use App\Actions\Financial\RequestWalletTopUpAction;
use App\Models\Transaction;
use App\Models\WalletTopUp;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use WireUi\Traits\WireUiActions;

new #[Title('My Wallet')] class extends Component {
    use WithPagination;
    use WithFileUploads;
    use WireUiActions;

    public bool $showTopUpModal = false;
    public $amount = '';
    public $reference = '';
    public $receipt;

    public function getShipperProperty()
    {
        return Auth::user()->shipper;
    }

    public function getWalletProperty()
    {
        return $this->shipper->wallet ?? $this->shipper->wallet()->create(['balance' => 0]);
    }

    #[Computed]
    public function topUps()
    {
        return WalletTopUp::where('shipper_id', $this->shipper->id)
            ->latest()
            ->paginate(5, ['*'], 'topUpsPage');
    }

    #[Computed]
    public function transactions()
    {
        return Transaction::where('wallet_id', $this->wallet->id)
            ->latest()
            ->paginate(10, ['*'], 'transactionsPage');
    }

    public function requestTopUp(RequestWalletTopUpAction $action)
    {
        $this->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'reference' => ['nullable', 'string', 'max:255'],
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ]);

        try {
            $action->execute(
                shipper: $this->shipper,
                amount: (float) $this->amount,
                receipt: $this->receipt,
                reference: $this->reference
            );

            $this->notification()->success('Top-Up requested successfully! Awaiting verification.');
            $this->reset(['amount', 'reference', 'receipt', 'showTopUpModal']);
            unset($this->topUps);
        } catch (\Exception $e) {
            $this->notification()->error('Error', $e->getMessage());
        }
    }
};
?>

<div>
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">My Wallet</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Manage your balance and funding requests</p>
        </div>
        <div class="flex items-center gap-3">
            <flux:button wire:click="$set('showTopUpModal', true)" variant="primary" icon="plus">Fund Wallet</flux:button>
        </div>
    </div>

    <!-- Balance Card -->
    <div class="mb-8 relative overflow-hidden bg-zinc-900 dark:bg-black rounded-3xl p-8 md:p-10 shadow-xl border border-zinc-800 group hover:shadow-2xl transition-all">
        <!-- Abstract gradient blur effects -->
        <div class="absolute -right-20 -top-20 w-64 h-64 rounded-full bg-emerald-500/20 blur-3xl group-hover:bg-emerald-500/30 transition-colors duration-500"></div>
        <div class="absolute -left-10 -bottom-10 w-48 h-48 rounded-full bg-indigo-500/20 blur-2xl group-hover:bg-indigo-500/30 transition-colors duration-500"></div>
        
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <flux:icon.wallet class="size-5 text-zinc-400" />
                    <p class="text-sm font-semibold text-zinc-400 uppercase tracking-widest">Available Balance</p>
                </div>
                <p class="text-5xl md:text-6xl font-black text-white tracking-tighter">
                    ${{ number_format($this->wallet->balance, 2) }}
                </p>
            </div>
            <div class="shrink-0 flex items-center justify-center p-5 bg-white/5 backdrop-blur-md rounded-2xl border border-white/10 hover:bg-white/10 transition-colors">
                <flux:icon.banknotes class="size-10 text-emerald-400 drop-shadow-md" />
            </div>
        </div>
    </div>

    <!-- Grid for Top-ups & Transactions -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <!-- Pending / Past Top-Ups -->
        <div>
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">Funding Requests</h2>
            <flux:table :paginate="$this->topUps">
                <flux:table.columns>
                    <flux:table.column>Date</flux:table.column>
                    <flux:table.column>Amount</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse($this->topUps as $topUp)
                        <flux:table.row :key="$topUp->id">
                            <flux:table.cell>{{ $topUp->created_at->format('M d, Y') }}</flux:table.cell>
                            <flux:table.cell>${{ number_format($topUp->amount, 2) }}</flux:table.cell>
                            <flux:table.cell>
                                @if($topUp->status->value === 'pending')
                                    <flux:badge color="amber" size="sm">Pending</flux:badge>
                                @elseif($topUp->status->value === 'approved')
                                    <flux:badge color="emerald" size="sm">Approved</flux:badge>
                                @else
                                    <flux:badge color="red" size="sm">Rejected</flux:badge>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3" class="text-center text-zinc-500">No funding requests found.</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        <!-- Ledger Transactions -->
        <div>
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">Transaction Ledger</h2>
            <flux:table :paginate="$this->transactions">
                <flux:table.columns>
                    <flux:table.column>Reference</flux:table.column>
                    <flux:table.column>Type</flux:table.column>
                    <flux:table.column>Amount</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse($this->transactions as $tx)
                        <flux:table.row :key="$tx->id">
                            <flux:table.cell>{{ Str::limit($tx->description ?? $tx->reference ?? '-', 20) }}</flux:table.cell>
                            <flux:table.cell>
                                @if($tx->type->value === 'credit')
                                    <flux:badge color="emerald" size="sm" icon="arrow-down-tray">Credit</flux:badge>
                                @else
                                    <flux:badge color="red" size="sm" icon="arrow-up-tray">Debit</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="font-medium {{ $tx->type->value === 'credit' ? 'text-emerald-600' : 'text-zinc-900' }}">
                                {{ $tx->type->value === 'credit' ? '+' : '-' }}${{ number_format($tx->amount, 2) }}
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3" class="text-center text-zinc-500">No transactions recorded.</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

    </div>

    <!-- Fund Wallet Modal -->
    <flux:modal wire:model="showTopUpModal" class="md:w-[32rem]">
        <form wire:submit="requestTopUp" class="space-y-6">
            <div>
                <flux:heading size="lg">Fund Wallet</flux:heading>
                <flux:subheading>Upload your transfer receipt to request a top-up.</flux:subheading>
            </div>

            <flux:input wire:model="amount" label="Amount (USD)" type="number" step="0.01" required icon="currency-dollar" placeholder="1000.00" />
            <flux:input wire:model="reference" label="Bank Reference / Memo (Optional)" placeholder="e.g. TR-98765" />
            
            <flux:input type="file" wire:model="receipt" label="Transfer Receipt" required accept="image/jpeg,image/png,application/pdf" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Submit Request</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
