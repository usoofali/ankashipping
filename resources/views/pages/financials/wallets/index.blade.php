<?php

declare(strict_types=1);

use App\Models\Wallet;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Wallets Management')] class extends Component {
    use WithPagination;

    public string $search = '';

    public function mount(): void
    {
        $this->authorize('wallets.view');
    }

    public function updatedSearch(): void
    {
        $this->resetPage('walletsPage');
    }

    #[Computed]
    public function metrics()
    {
        return [
            'total_balance' => Wallet::sum('balance'),
            'active_wallets' => Wallet::where('balance', '>', 0)->count(),
            'total_wallets' => Wallet::count(),
        ];
    }

    #[Computed]
    public function wallets()
    {
        return Wallet::with('shipper')
            ->when($this->search, function ($query) {
                $query->whereHas('shipper', function ($q) {
                    $q->where('company_name', 'like', "%{$this->search}%")
                      ->orWhere('name', 'like', "%{$this->search}%")
                      ->orWhere('email', 'like', "%{$this->search}%");
                });
            })
            ->orderByDesc('balance')
            ->paginate(20, ['*'], 'walletsPage');
    }


};
?>

<div>
    <x-crud.page-shell>
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
            <x-crud.page-header :heading="__('Master Wallets')" :subheading="__('Monitor all shipper balances and audit transaction ledgers.')" icon="wallet" class="!mb-0" />
            
            <div class="flex items-center gap-3">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search shippers...')" clearable class="w-64" />
            </div>
        </div>

        <!-- Metric Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Total Balance Card -->
            <div class="relative overflow-hidden bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-sm group hover:shadow-md transition-shadow">
                <div class="absolute -right-6 -top-6 text-emerald-50 dark:text-emerald-900/20 group-hover:scale-110 transition-transform duration-500">
                    <flux:icon.banknotes class="size-32" />
                </div>
                <div class="relative z-10">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="p-2 bg-emerald-100 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 rounded-lg shrink-0">
                            <flux:icon.banknotes class="size-4" />
                        </div>
                        <p class="text-[13px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider truncate">Total Network Balance</p>
                    </div>
                    <p class="text-4xl font-black text-zinc-900 dark:text-white tracking-tight">${{ number_format($this->metrics['total_balance'], 2) }}</p>
                </div>
            </div>

            <!-- Active Wallets Card -->
            <div class="relative overflow-hidden bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-sm group hover:shadow-md transition-shadow">
                <div class="absolute -right-6 -top-6 text-indigo-50 dark:text-indigo-900/20 group-hover:scale-110 transition-transform duration-500">
                    <flux:icon.check-circle class="size-32" />
                </div>
                <div class="relative z-10">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="p-2 bg-indigo-100 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 rounded-lg shrink-0">
                            <flux:icon.check-circle class="size-4" />
                        </div>
                        <p class="text-[13px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider truncate">Active Funded Wallets</p>
                    </div>
                    <p class="text-4xl font-black text-zinc-900 dark:text-white tracking-tight">{{ number_format($this->metrics['active_wallets']) }}</p>
                </div>
            </div>

            <!-- Total Wallets Card -->
            <div class="relative overflow-hidden bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-sm group hover:shadow-md transition-shadow">
                <div class="absolute -right-6 -top-6 text-sky-50 dark:text-sky-900/20 group-hover:scale-110 transition-transform duration-500">
                    <flux:icon.wallet class="size-32" />
                </div>
                <div class="relative z-10">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="p-2 bg-sky-100 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 rounded-lg shrink-0">
                            <flux:icon.wallet class="size-4" />
                        </div>
                        <p class="text-[13px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider truncate">Total Registered Wallets</p>
                    </div>
                    <p class="text-4xl font-black text-zinc-900 dark:text-white tracking-tight">{{ number_format($this->metrics['total_wallets']) }}</p>
                </div>
            </div>
        </div>

        <!-- Master Data Table -->
        <x-crud.panel class="p-6">
            <flux:table :paginate="$this->wallets">
                <flux:table.columns>
                    <flux:table.column icon="building-office">Shipper</flux:table.column>
                    <flux:table.column icon="banknotes">Current Balance</flux:table.column>
                    <flux:table.column icon="clock">Last Updated</flux:table.column>
                    <flux:table.column align="right">Actions</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse($this->wallets as $wallet)
                        <flux:table.row :key="$wallet->id">
                            <flux:table.cell>
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $wallet->shipper->company_name ?? 'Shipper #'.$wallet->shipper_id }}</div>
                                <div class="text-xs text-zinc-500">{{ $wallet->shipper->email ?? '' }}</div>
                            </flux:table.cell>
                            <flux:table.cell class="font-bold {{ $wallet->balance > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-500 dark:text-zinc-400' }}">
                                ${{ number_format($wallet->balance, 2) }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <span class="text-xs text-zinc-500" title="{{ $wallet->updated_at }}">{{ $wallet->updated_at->diffForHumans() }}</span>
                            </flux:table.cell>
                            <flux:table.cell align="right">
                                <flux:button href="{{ route('financials.wallets.show', $wallet->id) }}" wire:navigate size="sm" variant="ghost" class="text-indigo-600 hover:text-indigo-700" icon="clipboard-document-list">View Statement</flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="text-center text-zinc-500 py-8">
                                No wallets found matching your search.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </x-crud.panel>
    </x-crud.page-shell>
</div>
