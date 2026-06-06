<?php

declare(strict_types=1);

namespace App\Livewire\Whatsapp\UserStats;

use App\Modules\WhatsApp\Models\WhatsAppUserStat;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use WireUi\Traits\WireUiActions;

new #[Title('WhatsApp Usage')] class extends Component {
    use WithPagination, WireUiActions;

    public string $search = '';
    public string $roleFilter = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => WhatsAppUserStat::count(),
            'shippers' => WhatsAppUserStat::where('contact_role', 'shipper')->count(),
            'staff' => WhatsAppUserStat::where('contact_role', 'staff')->count(),
            'drivers' => WhatsAppUserStat::where('contact_role', 'driver')->count(),
        ];
    }

    #[Computed]
    public function userStats()
    {
        return WhatsAppUserStat::query()
            ->when($this->search !== '', function (Builder $q) {
                $q->where(function (Builder $sub) {
                    $sub->where('phone_number', 'like', '%' . $this->search . '%')
                        ->orWhere('contact_name', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->roleFilter !== '', function (Builder $q) {
                $q->where('contact_role', $this->roleFilter);
            })
            ->orderByDesc('last_contact_at')
            ->paginate(20);
    }
}; ?>

<x-crud.page-shell>
    <div class="flex items-center justify-between mb-8">
        <x-crud.page-header :heading="__('WhatsApp Usage')"
            :subheading="__('Track how frequently each contact uses the WhatsApp channel.')"
            icon="chat-bubble-left-right" class="mb-0!" />
    </div>

    {{-- Stat Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="relative overflow-hidden bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-sm hover:shadow-md transition-shadow">
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-zinc-100 dark:bg-zinc-800 rounded-full opacity-50 blur-2xl"></div>
            <div class="relative z-10 flex flex-col justify-between h-full space-y-4">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400">
                        <flux:icon.users class="w-5 h-5" />
                    </div>
                    <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">{{ __('Total Users') }}</span>
                </div>
                <div class="text-4xl font-bold text-zinc-900 dark:text-white">{{ number_format($this->stats['total']) }}</div>
            </div>
        </div>

        <div class="relative overflow-hidden bg-linear-to-br from-white to-sky-50 dark:from-zinc-900 dark:to-sky-950/20 border border-sky-100 dark:border-sky-900/50 rounded-xl p-6 shadow-sm hover:shadow-md transition-shadow">
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-sky-200 dark:bg-sky-900 rounded-full opacity-40 blur-2xl"></div>
            <div class="relative z-10 flex flex-col justify-between h-full space-y-4">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-sky-100 dark:bg-sky-900/50 text-sky-600 dark:text-sky-400">
                        <flux:icon.building-office class="w-5 h-5" />
                    </div>
                    <span class="text-sm font-medium text-sky-600 dark:text-sky-400 uppercase tracking-wider">{{ __('Shippers') }}</span>
                </div>
                <div class="text-4xl font-bold text-zinc-900 dark:text-white">{{ number_format($this->stats['shippers']) }}</div>
            </div>
        </div>

        <div class="relative overflow-hidden bg-linear-to-br from-white to-violet-50 dark:from-zinc-900 dark:to-violet-950/20 border border-violet-100 dark:border-violet-900/50 rounded-xl p-6 shadow-sm hover:shadow-md transition-shadow">
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-violet-200 dark:bg-violet-900 rounded-full opacity-40 blur-2xl"></div>
            <div class="relative z-10 flex flex-col justify-between h-full space-y-4">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-violet-100 dark:bg-violet-900/50 text-violet-600 dark:text-violet-400">
                        <flux:icon.user-circle class="w-5 h-5" />
                    </div>
                    <span class="text-sm font-medium text-violet-600 dark:text-violet-400 uppercase tracking-wider">{{ __('Staff') }}</span>
                </div>
                <div class="text-4xl font-bold text-zinc-900 dark:text-white">{{ number_format($this->stats['staff']) }}</div>
            </div>
        </div>

        <div class="relative overflow-hidden bg-linear-to-br from-white to-amber-50 dark:from-zinc-900 dark:to-amber-950/20 border border-amber-100 dark:border-amber-900/50 rounded-xl p-6 shadow-sm hover:shadow-md transition-shadow">
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-amber-200 dark:bg-amber-900 rounded-full opacity-40 blur-2xl"></div>
            <div class="relative z-10 flex flex-col justify-between h-full space-y-4">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-400">
                        <flux:icon.truck class="w-5 h-5" />
                    </div>
                    <span class="text-sm font-medium text-amber-600 dark:text-amber-400 uppercase tracking-wider">{{ __('Drivers') }}</span>
                </div>
                <div class="text-4xl font-bold text-zinc-900 dark:text-white">{{ number_format($this->stats['drivers']) }}</div>
            </div>
        </div>
    </div>

    <x-crud.panel class="p-6">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4 w-full sm:w-auto">
                <div class="w-full sm:w-72">
                    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                        :placeholder="__('Search name or number...')" />
                </div>
                <div class="w-full sm:w-48">
                    <flux:select wire:model.live="roleFilter" :placeholder="__('All Roles')">
                        <flux:select.option value="">{{ __('All Roles') }}</flux:select.option>
                        <flux:select.option value="shipper">{{ __('Shippers') }}</flux:select.option>
                        <flux:select.option value="staff">{{ __('Staff') }}</flux:select.option>
                        <flux:select.option value="driver">{{ __('Drivers') }}</flux:select.option>
                        <flux:select.option value="unknown">{{ __('Unknown') }}</flux:select.option>
                    </flux:select>
                </div>
            </div>
        </div>

        <flux:table :paginate="$this->userStats">
            <flux:table.columns sticky class="bg-white dark:bg-zinc-900">
                <flux:table.column>{{ __('Contact') }}</flux:table.column>
                <flux:table.column>{{ __('Phone') }}</flux:table.column>
                <flux:table.column>{{ __('Role') }}</flux:table.column>
                <flux:table.column>{{ __('Messages') }}</flux:table.column>
                <flux:table.column>{{ __('Conversations') }}</flux:table.column>
                <flux:table.column>{{ __('First Contact') }}</flux:table.column>
                <flux:table.column>{{ __('Last Active') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->userStats as $stat)
                    <flux:table.row :key="$stat->id">
                        <flux:table.cell class="font-medium">
                            {{ $stat->contact_name ?? __('Unknown') }}
                        </flux:table.cell>
                        <flux:table.cell class="text-sm text-zinc-500 font-mono">
                            {{ $stat->phone_number }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($stat->contact_role === 'shipper')
                                <flux:badge size="sm" color="sky" inset="left">{{ __('Shipper') }}</flux:badge>
                            @elseif ($stat->contact_role === 'staff')
                                <flux:badge size="sm" color="violet" inset="left">{{ __('Staff') }}</flux:badge>
                            @elseif ($stat->contact_role === 'driver')
                                <flux:badge size="sm" color="amber" inset="left">{{ __('Driver') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc" inset="left">{{ __('Unknown') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <span class="font-semibold text-zinc-800 dark:text-zinc-100">
                                {{ number_format($stat->total_messages) }}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ number_format($stat->conversation_count) }}
                        </flux:table.cell>
                        <flux:table.cell class="text-xs text-zinc-500 whitespace-nowrap">
                            {{ $stat->first_contact_at?->format('M j, Y') ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell class="text-xs text-zinc-500 whitespace-nowrap">
                            {{ $stat->last_contact_at?->diffForHumans() ?? '—' }}
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="text-center text-zinc-500 py-8">
                            {{ __('No WhatsApp usage data yet.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </x-crud.panel>
</x-crud.page-shell>
