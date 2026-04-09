<?php

declare(strict_types=1);

use App\Models\Wallet;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Title('Wallet Statement')] class extends Component {
    use WithPagination;

    public Wallet $wallet;

    public function mount(Wallet $wallet): void
    {
        $this->authorize('wallets.view');
        $this->wallet = $wallet->load('shipper');
    }

    #[Computed]
    public function transactions()
    {
        return Transaction::where('wallet_id', $this->wallet->id)
            ->latest()
            ->paginate(50);
    }

    public function exportCsv(): StreamedResponse
    {
        $this->authorize('wallets.view');
        
        $transactions = Transaction::where('wallet_id', $this->wallet->id)->latest()->get();
        
        $csvHeader = "Date,Reference,Description,Type,Amount\n";
        $csvData = $transactions->map(function($t) {
            $type = $t->type->value === 'credit' ? '+' : '-';
            return '"'.$t->created_at->format('Y-m-d H:i:s').'","'.($t->reference ?? '').'","'.($t->description ?? '').'",'.$type.',"'.number_format($t->amount, 2, '.', '').'"';
        })->implode("\n");

        return response()->streamDownload(function () use ($csvHeader, $csvData) {
            echo $csvHeader . $csvData;
        }, 'Wallet_Statement_Shipper_'.$this->wallet->shipper_id.'_'.now()->format('Ymd').'.csv');
    }
};
?>

<div>
    <x-crud.page-shell>
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4 print:hidden">
            <div>
                <x-crud.page-header :heading="__('Statement of Account')" :subheading="__('Ledger history for ' . ($this->wallet->shipper->company_name ?? 'Shipper #'.$this->wallet->shipper_id))" icon="document-text" class="!mb-0" />
                <a href="{{ route('financials.wallets.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 flex items-center gap-1 mt-2" wire:navigate>
                    <flux:icon.arrow-left class="size-4" /> Back to Master Wallets
                </a>
            </div>
            
            <div class="flex items-center gap-3">
                <flux:button wire:click="exportCsv" variant="ghost" icon="document-arrow-down">Export CSV</flux:button>
                <flux:button onclick="window.print()" variant="primary" icon="printer">Print Statement</flux:button>
            </div>
        </div>

        <!-- Print-only Header -->
        <div class="hidden print:block mb-8">
            <h1 class="text-2xl font-bold">Statement of Account</h1>
            <p class="text-zinc-600">Generated on {{ now()->format('M d, Y H:i') }}</p>
            <div class="mt-4 p-4 border border-zinc-300 rounded">
                <p><strong>Shipper:</strong> {{ $this->wallet->shipper->company_name ?? 'Shipper #'.$this->wallet->shipper_id }}</p>
                <p><strong>Email:</strong> {{ $this->wallet->shipper->email ?? 'N/A' }}</p>
                <p><strong>Current Balance:</strong> ${{ number_format($this->wallet->balance, 2) }}</p>
            </div>
        </div>

        <!-- Screen-only Balance Highlight -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 print:hidden">
            <div class="relative overflow-hidden bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-sm">
                <div class="absolute -right-6 -top-6 text-emerald-50 dark:text-emerald-900/20">
                    <flux:icon.banknotes class="size-32" />
                </div>
                <div class="relative z-10">
                    <p class="text-[13px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider mb-2">Current Balance</p>
                    <p class="text-4xl font-black text-emerald-600 dark:text-emerald-400 tracking-tight">${{ number_format($this->wallet->balance, 2) }}</p>
                </div>
            </div>
        </div>

        <!-- Ledger Data Table -->
        <x-crud.panel class="p-6">
            <flux:table :paginate="$this->transactions">
                <flux:table.columns>
                    <flux:table.column icon="calendar">Date</flux:table.column>
                    <flux:table.column icon="hashtag">Reference</flux:table.column>
                    <flux:table.column icon="document-text">Memo / Description</flux:table.column>
                    <flux:table.column icon="arrows-up-down">Type</flux:table.column>
                    <flux:table.column icon="currency-dollar" align="right">Amount</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse($this->transactions as $tx)
                        <flux:table.row :key="$tx->id">
                            <flux:table.cell class="whitespace-nowrap">{{ $tx->created_at->format('M d, Y H:i') }}</flux:table.cell>
                            <flux:table.cell>{{ $tx->reference ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ Str::limit($tx->description ?? '-', 50) }}</flux:table.cell>
                            <flux:table.cell>
                                @if($tx->type->value === 'credit')
                                    <flux:badge color="emerald" size="sm" icon="arrow-down-tray">Credit</flux:badge>
                                @else
                                    <flux:badge color="red" size="sm" icon="arrow-up-tray">Debit</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="right" class="font-medium {{ $tx->type->value === 'credit' ? 'text-emerald-600' : 'text-zinc-900 dark:text-zinc-100' }}">
                                {{ $tx->type->value === 'credit' ? '+' : '-' }}${{ number_format($tx->amount, 2) }}
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="text-center text-zinc-500 py-8">
                                No transactions found in this ledger.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </x-crud.panel>
    </x-crud.page-shell>
    
    <style>
        @media print {
            body { background: white !important; color: black !important; }
            .bg-white, .dark\:bg-zinc-900 { background: white !important; border-color: #ddd !important; border-width: 1px !important; }
            .text-zinc-900, .text-zinc-100, .dark\:text-white { color: black !important; }
            .shadow-sm { box-shadow: none !important; }
            nav, .sidebar { display: none !important; }
        }
    </style>
</div>
