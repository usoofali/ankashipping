<?php

declare(strict_types=1);

use App\Models\Prealert;
use App\Models\Shipper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use WireUi\Traits\WireUiActions;

new #[Title('Prealerts')] class extends Component {
    use WithPagination;
    use WireUiActions;

    #[Url(as: 'shipper')]
    public string $shipperFilter = '';

    public function mount(): void
    {
        // Access control: Shippers see theirs, Staff see all.
        // Handled in the query.
    }

    #[Computed]
    public function prealerts()
    {
        $user = Auth::user();
        $query = Prealert::query()
            ->with(['shipper.user', 'vehicle', 'carrier', 'destinationPort.state', 'destinationPort.country'])
            ->latest();

        if ($user?->staff()->exists() || $user?->hasRole('super_admin')) {
            if ($this->shipperFilter) {
                $query->where('shipper_id', $this->shipperFilter);
            }
        } else {
            // It's a shipper
            $shipperId = $user?->shipper?->id;
            if ($shipperId) {
                $query->where('shipper_id', $shipperId);
            } else {
                return collect(); // Or handle error
            }
        }

        return $query->paginate(15);
    }

    #[Computed]
    public function shippers()
    {
        if (!Auth::user()?->hasRole('super_admin') && !Auth::user()?->staff()->exists()) {
            return collect();
        }

        return Shipper::query()->with('user:id,name')->get()->map(fn($s) => [
            'id' => $s->id,
            'name' => $s->company_name ?: $s->user?->name,
        ]);
    }

    public ?Prealert $selectedPrealert = null;

    public bool $showDeleteModal = false;

    public ?int $prealertPendingDeleteId = null;

    public string $prealertPendingDeleteLabel = '';

    public function updatedShowDeleteModal(bool $value): void
    {
        if (! $value) {
            $this->prealertPendingDeleteId = null;
            $this->prealertPendingDeleteLabel = '';
        }
    }

    public function openReviewModal(int $id): void
    {
        $this->selectedPrealert = Prealert::with(['shipper.user', 'vehicle', 'carrier', 'destinationPort.state', 'destinationPort.country'])->findOrFail($id);
        $this->dispatch('modal-show', name: 'review-prealert');
    }

    public function openDeleteModal(int $id): void
    {
        $prealert = Prealert::query()->findOrFail($id);
        $this->authorize('delete', $prealert);

        $this->prealertPendingDeleteId = $prealert->id;
        $this->prealertPendingDeleteLabel = filled($prealert->vin)
            ? (string) $prealert->vin
            : __('Prealert #:id', ['id' => $prealert->id]);
        $this->showDeleteModal = true;
    }

    public function confirmDeletePrealert(): void
    {
        if ($this->prealertPendingDeleteId === null) {
            return;
        }

        $prealert = Prealert::query()->findOrFail($this->prealertPendingDeleteId);
        $this->authorize('delete', $prealert);

        $prealert->delete();

        $this->showDeleteModal = false;
        $this->prealertPendingDeleteId = null;
        $this->prealertPendingDeleteLabel = '';

        $this->resetPage();

        $this->notification()->success(
            title: __('Deleted'),
            description: __('Prealert has been deleted.')
        );
    }

    public function convertToShipment(int $id): void
    {
        $this->redirect(route('shipments.create', ['prealert' => $id]), navigate: true);
    }

    public function updatedShipperFilter(): void
    {
        $this->resetPage();
    }
}; ?>

<x-crud.page-shell>
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <div class="rounded-lg bg-zinc-100 p-2 dark:bg-zinc-800">
                <flux:icon.bell class="size-6 text-zinc-600 dark:text-zinc-400" />
            </div>
            <x-crud.page-header :heading="__('Prealerts')" :subheading="__('Incoming vehicle alerts and submissions.')"
                class="!mb-0" />
        </div>
    </div>

    <div class="mb-6 flex flex-wrap items-center gap-4">
        <div class="flex-1 min-w-[200px]">
            <div class="flex items-center gap-4 w-full md:w-auto">
                {{-- Shipper Filter --}}
                @if (Auth::user()?->hasRole('super_admin') || Auth::user()?->staff()->exists())
                    <div class="w-full md:w-64">
                        <flux:select wire:model.live="shipperFilter" placeholder="{{ __('Filter by Shipper') }}">
                            <flux:select.option value="">{{ __('All Shippers') }}</flux:select.option>
                            @foreach ($this->shippers as $shipper)
                                <flux:select.option value="{{ $shipper['id'] }}">{{ $shipper['name'] }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if ($this->prealerts->isEmpty())
        <x-crud.empty-state icon="bell-slash" :title="__('No prealerts found')" :description="__('Try adjusting your filters or create a new prealert.')" />
    @else
        <x-crud.panel class="p-6">
            <flux:table :paginate="$this->prealerts">
                <flux:table.columns>
                    <flux:table.column>{{ __('VIN / Lot #') }}</flux:table.column>
                    <flux:table.column>{{ __('Shipper') }}</flux:table.column>
                    <flux:table.column>{{ __('Vehicle') }}</flux:table.column>
                    <flux:table.column>{{ __('Auction / Location') }}</flux:table.column>
                    <flux:table.column>{{ __('Destination') }}</flux:table.column>
                    <flux:table.column>{{ __('Created') }}</flux:table.column>
                    <flux:table.column>{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->prealerts as $prealert)
                        <flux:table.row :key="$prealert->id">
                            <flux:table.cell>
                                <div class="flex flex-col">
                                    <span class="font-mono text-xs font-semibold text-zinc-900! dark:text-zinc-100!">
                                        {{ $prealert->vin }}
                                    </span>
                                    <span class="text-[10px] text-zinc-500 font-mono">
                                        {{ $prealert->vehicle?->lot_number ?: '—' }}
                                    </span>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex flex-col">
                                    <span class="font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $prealert->shipper?->user?->name }}
                                    </span>
                                    @if ($prealert->shipper?->company_name)
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $prealert->shipper->company_name }}
                                        </span>
                                    @endif
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($prealert->vehicle)
                                    <span class="text-zinc-600 dark:text-zinc-400">
                                        {{ $prealert->vehicle->year }} {{ $prealert->vehicle->make }}
                                        {{ $prealert->vehicle->model }}
                                        <span class="text-xs text-zinc-400 ml-1">({{ $prealert->vehicle->color }})</span>
                                    </span>
                                @else
                                    <span class="text-zinc-400 italic">{{ __('N/A') }}</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex flex-col">
                                    <span class="text-xs font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $prealert->vehicle?->auction_name ?: '—' }}
                                    </span>
                                    <span class="text-[10px] text-zinc-500 truncate max-w-[150px]"
                                        title="{{ $prealert->vehicle?->location }}">
                                        {{ $prealert->vehicle?->location ?: '—' }}
                                    </span>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($prealert->destinationPort)
                                    <span class="text-xs font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ $prealert->destinationPort->name }}
                                        ({{ $prealert->destinationPort->state?->code ?? '—' }} -
                                        {{ $prealert->destinationPort->country?->iso2 ?? '—' }})
                                    </span>
                                @else
                                    <span class="text-zinc-400 italic text-xs">—</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="text-zinc-500">
                                {{ $prealert->created_at?->diffForHumans() ?: '—' }}
                            </flux:table.cell>
                            <flux:table.cell align="right">
                                <flux:dropdown align="end" variant="ghost">
                                    <flux:button variant="ghost" icon="ellipsis-horizontal" size="sm" />
                                    <flux:menu>
                                        <flux:menu.item icon="eye" wire:click="openReviewModal({{ $prealert->id }})" wire:key="view-{{ $prealert->id }}">
                                            {{ __('View Details') }}
                                        </flux:menu.item>

                                        @if (auth()->user()?->hasRole('super_admin') || auth()->user()?->staff()->exists())
                                            <flux:menu.item icon="truck" wire:click="convertToShipment({{ $prealert->id }})" wire:key="convert-{{ $prealert->id }}">
                                                {{ __('Convert to Shipment') }}
                                            </flux:menu.item>
                                        @endif

                                        @can('delete', $prealert)
                                            <flux:menu.separator />

                                            <flux:menu.item icon="trash" variant="danger" wire:click="openDeleteModal({{ $prealert->id }})" wire:key="delete-{{ $prealert->id }}">
                                                {{ __('Delete') }}
                                            </flux:menu.item>
                                        @endcan
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-crud.panel>
    @endif

    {{-- Review Modal --}}
    <flux:modal name="review-prealert" variant="large" class="space-y-6">
        @if ($selectedPrealert)
            <div>
                <flux:heading size="lg">{{ __('Review Prealert') }}</flux:heading>
                <flux:subheading>{{ __('Carefully review the vehicle and documentation before approving.') }}
                </flux:subheading>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <x-crud.panel class="p-4 bg-zinc-50 dark:bg-zinc-800/50">
                        <flux:heading size="sm" class="mb-2 uppercase tracking-wider text-zinc-500">
                            {{ __('Vehicle Information') }}</flux:heading>
                        @if ($selectedPrealert->vehicle)
                            <div class="font-bold text-lg">{{ $selectedPrealert->vehicle->year }}
                                {{ $selectedPrealert->vehicle->make }} {{ $selectedPrealert->vehicle->model }}</div>
                            <div class="text-sm text-zinc-600 dark:text-zinc-400 font-mono mt-1">{{ __('VIN') }}:
                                {{ $selectedPrealert->vin }}</div>
                        @else
                            <div class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('VIN') }}: {{ $selectedPrealert->vin }}
                            </div>
                            <div class="text-xs text-red-500 mt-1 italic">{{ __('Vehicle data not fetched.') }}</div>
                        @endif
                    </x-crud.panel>

                    <div class="space-y-2">
                        <flux:label size="sm" class="uppercase tracking-wider text-zinc-500">{{ __('Logistics') }}
                        </flux:label>
                        <div class="text-sm">
                            <span class="font-semibold">{{ __('Year/Make/Model') }}:</span>
                            {{ $selectedPrealert->vehicle?->year }} {{ $selectedPrealert->vehicle?->make }}
                            {{ $selectedPrealert->vehicle?->model }}
                        </div>
                        <div class="text-sm">
                            <span class="font-semibold">{{ __('Vehicle Status') }}:</span>
                            @if ($selectedPrealert->vehicle?->vehicle_is)
                                <flux:badge size="sm" color="zinc" variant="subtle">{{ $selectedPrealert->vehicle->vehicle_is }}</flux:badge>
                            @else
                                —
                            @endif
                        </div>
                        <div class="text-sm">
                            <span class="font-semibold">{{ __('Color') }}:</span>
                            {{ $selectedPrealert->vehicle?->color ?: '—' }}
                        </div>
                        <div class="text-sm">
                            <span class="font-semibold">{{ __('Lot Number') }}:</span>
                            {{ $selectedPrealert->vehicle?->lot_number ?: '—' }}
                        </div>
                        <div class="text-sm">
                            <span class="font-semibold">{{ __('Auction') }}:</span>
                            {{ $selectedPrealert->vehicle?->auction_name ?: '—' }}
                        </div>
                        <div class="text-sm">
                            <span class="font-semibold">{{ __('Pickup Location') }}:</span>
                            {{ $selectedPrealert->vehicle?->location ?: '—' }}
                        </div>
                        <div class="text-sm">
                            <span class="font-semibold">{{ __('Carrier') }}:</span>
                            {{ $selectedPrealert->carrier?->name ?: '—' }}
                        </div>
                        <div class="text-sm">
                            <span class="font-semibold">{{ __('Destination') }}:</span>
                            @if($selectedPrealert->destinationPort)
                                {{ $selectedPrealert->destinationPort->name }}
                                ({{ $selectedPrealert->destinationPort->state?->code ?? '—' }} -
                                {{ $selectedPrealert->destinationPort->country?->iso2 ?? '—' }})
                            @else
                                —
                            @endif
                        </div>
                        @if ($selectedPrealert->gatepass_pin)
                            <div class="text-sm">
                                <span class="font-semibold">{{ __('Gatepass PIN') }}:</span> <span
                                    class="font-mono">{{ $selectedPrealert->gatepass_pin }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="space-y-4">
                    <flux:label size="sm" class="uppercase tracking-wider text-zinc-500">{{ __('Action Receipt') }}
                    </flux:label>
                    @if ($selectedPrealert->auction_receipt)
                        @php
                            $extension = pathinfo($selectedPrealert->auction_receipt, PATHINFO_EXTENSION);
                            $isPdf = strtolower($extension) === 'pdf';
                        @endphp

                        <div class="rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-700 shadow-sm transition-transform hover:scale-[1.02] bg-white dark:bg-zinc-900">
                            @if ($isPdf)
                                <div class="h-48 flex flex-col items-center justify-center bg-zinc-50 dark:bg-zinc-800/50 p-6">
                                    <flux:icon.document-text class="size-16 text-zinc-400 mb-2" />
                                    <span class="text-xs font-medium text-zinc-500 uppercase tracking-tighter">{{ __('PDF Receipt') }}</span>
                                </div>
                            @else
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($selectedPrealert->auction_receipt) }}"
                                    class="w-full h-auto max-h-64 object-cover" alt="Action Receipt">
                            @endif
                            <div
                                class="p-2 bg-white dark:bg-zinc-900 border-t border-zinc-200 dark:border-zinc-700 text-center">
                                <flux:link :href="\Illuminate\Support\Facades\Storage::url($selectedPrealert->auction_receipt)" target="_blank" size="xs"
                                    icon="external-link">{{ __('View Full Receipt') }}</flux:link>
                            </div>
                        </div>
                    @else
                        <div
                            class="h-40 flex items-center justify-center bg-zinc-100 dark:bg-zinc-800 rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-700">
                            <span class="text-zinc-400 italic text-sm">{{ __('No receipt uploaded') }}</span>
                        </div>
                    @endif
                </div>

                <div class="md:col-span-2">
                    <flux:label size="sm" class="uppercase tracking-wider text-zinc-500">{{ __('Shipper Notes') }}
                    </flux:label>
                    <div
                        class="mt-1 p-3 bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 text-sm text-zinc-700 dark:text-zinc-300 min-h-[60px]">
                        {{ $selectedPrealert->notes ?: __('No notes provided.') }}
                    </div>
                </div>

                <div class="md:col-span-2 mt-4 flex justify-end gap-3">
                    <flux:button variant="ghost" wire:click="$set('selectedPrealert', null)">
                        {{ __('Close') }}
                    </flux:button>
                    @if (auth()->user()?->hasRole('super_admin') || auth()->user()?->staff()->exists())
                        <flux:button variant="primary" icon="truck" wire:click="convertToShipment({{ $selectedPrealert->id }})">
                            {{ __('Convert to Shipment') }}
                        </flux:button>
                    @endif
                </div>
            </div>
        @endif
    </flux:modal>

    <flux:modal wire:model.self="showDeleteModal" class="max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Delete prealert?') }}</flux:heading>
            <flux:subheading>
                {{ __('This will permanently remove this prealert and cannot be undone.') }}
            </flux:subheading>
            @if ($prealertPendingDeleteLabel !== '')
                <flux:text class="font-mono font-medium text-zinc-900 dark:text-zinc-100">
                    {{ $prealertPendingDeleteLabel }}
                </flux:text>
            @endif
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" type="button" wire:click="confirmDeletePrealert" wire:loading.attr="disabled">
                {{ __('Delete') }}
            </flux:button>
        </div>
    </flux:modal>
</x-crud.page-shell>