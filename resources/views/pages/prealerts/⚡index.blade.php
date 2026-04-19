<?php

declare(strict_types=1);

use App\Models\Prealert;
use App\Models\Shipper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Vehicle;
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
            ->with(['shipper.user', 'vehicles', 'shipment', 'carrier', 'destinationPort.state', 'destinationPort.country'])
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
        if (!$value) {
            $this->prealertPendingDeleteId = null;
            $this->prealertPendingDeleteLabel = '';
        }
    }

    public function openReviewModal(int $id): void
    {
        $this->selectedPrealert = Prealert::with(['shipper.user', 'vehicles.shipment', 'shipment', 'carrier', 'destinationPort.state', 'destinationPort.country'])->findOrFail($id);
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
        Vehicle::where('prealert_id', $prealert->id)->update(['prealert_id' => null]);
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
                <flux:table.columns sticky class="bg-white dark:bg-zinc-900">
                    <flux:table.column>{{ __('VIN / Lot #') }}</flux:table.column>
                    <flux:table.column>{{ __('Shipper') }}</flux:table.column>
                    <flux:table.column>{{ __('Vehicle') }}</flux:table.column>
                    <flux:table.column>{{ __('Auction / Location') }}</flux:table.column>
                    <flux:table.column>{{ __('Destination') }}</flux:table.column>
                    <flux:table.column>{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->prealerts as $prealert)
                        <flux:table.row :key="$prealert->id">
                            <flux:table.cell>
                                @if($prealert->vehicles->count() > 1)
                                    <div class="flex flex-col">
                                        <span class="font-bold text-zinc-900 dark:text-white">{{ $prealert->vehicles->count() }}
                                            {{ __('Vehicles') }}</span>
                                        <p class="text-[13px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider whitespace-normal">{{ $prealert->shipping_mode?->name }}</p>
                                    </div>
                                @else
                                    <div class="flex flex-col">
                                        <span class="font-mono text-xs font-semibold text-zinc-900! dark:text-zinc-100!">
                                            {{ $prealert->vehicles->first()?->vin ?: '—' }}
                                        </span>
                                        <span class="text-[10px] text-zinc-500 font-mono">
                                            {{ $prealert->vehicles->first()?->lot_number ?: '—' }}
                                        </span>
                                    </div>
                                @endif
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
                                @if ($prealert->vehicles->isNotEmpty())
                                    <div class="flex flex-col">
                                        
                                            @if($prealert->vehicles->count() === 1)
                                                <span class="font-medium text-zinc-900 dark:text-zinc-100">
                                                    {{ $prealert->vehicles->first()->year }} {{ $prealert->vehicles->first()->make }}
                                                    {{ $prealert->vehicles->first()->model }}
                                                </span>
                                                <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                                    ({{ $prealert->vehicles->first()->color }})
                                                </span>
                                            @else
                                                <span class="text-zinc-600 dark:text-zinc-400">
                                                    {{ $prealert->vehicles->first()->make }}... +{{ $prealert->vehicles->count() - 1 }}
                                                </span>
                                            @endif

                                    </div>
                                @else
                                    <span class="text-zinc-400 italic">{{ __('N/A') }}</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex flex-col">
                                    <span class="text-xs font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $prealert->vehicles->first()?->auction_name ?: '—' }}
                                    </span>
                                    <span class="text-[10px] text-zinc-500 whitespace-normal break-words"
                                        title="{{ $prealert->vehicles->first()?->location }}">
                                        {{ $prealert->vehicles->first()?->location ?: '—' }}
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
                            <flux:table.cell align="right">
                                <flux:dropdown align="end" variant="ghost">
                                    <flux:button variant="ghost" icon="ellipsis-horizontal" size="sm" />
                                    <flux:menu>
                                        <flux:menu.item icon="eye" wire:click="openReviewModal({{ $prealert->id }})"
                                            wire:key="view-{{ $prealert->id }}">
                                            {{ __('View Details') }}
                                        </flux:menu.item>

                                        @if (auth()->user()?->hasRole('super_admin') || auth()->user()?->staff()->exists())
                                            <flux:menu.item icon="car-front" wire:click="convertToShipment({{ $prealert->id }})"
                                                wire:key="convert-{{ $prealert->id }}">
                                                {{ __('Convert to Shipment') }}
                                            </flux:menu.item>
                                        @endif

                                        @can('delete', $prealert)
                                            <flux:menu.separator />

                                            <flux:menu.item icon="trash" variant="danger"
                                                wire:click="openDeleteModal({{ $prealert->id }})"
                                                wire:key="delete-{{ $prealert->id }}">
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

            <div class="flex flex-col gap-6">
                {{-- Top: Logistics & Status --}}
                <div class="space-y-6">
                    <x-crud.panel class="p-4 bg-zinc-50 dark:bg-zinc-800/50">
                        <flux:heading size="sm" class="mb-4 uppercase tracking-wider text-zinc-500">
                            {{ __('Prealert Logistics') }}
                        </flux:heading>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <div>
                                <flux:label size="xs" class="uppercase text-zinc-400">{{ __('Shipping Mode') }}</flux:label>
                                <div class="flex items-center gap-2 mt-1">
                                    <flux:icon
                                        :name="$selectedPrealert->shipping_mode === \App\Enums\ShippingMode::Container ? 'container' : 'car-front'"
                                        class="size-4 text-zinc-500" />
                                    <span
                                        class="font-bold text-zinc-900 dark:text-white">{{ $selectedPrealert->shipping_mode?->name }}</span>
                                </div>
                            </div>

                            @if($selectedPrealert->shipment)
                                <div>
                                    <flux:label size="xs" class="uppercase text-zinc-400">{{ __('Target Container') }}
                                    </flux:label>
                                    <div class="mt-1">
                                        <flux:badge color="zinc" variant="solid" size="sm">
                                            {{ $selectedPrealert->shipment->reference_no }}</flux:badge>
                                    </div>
                                </div>
                            @endif

                            <div>
                                <flux:label size="xs" class="uppercase text-zinc-400">{{ __('Carrier') }}</flux:label>
                                <div class="text-sm font-medium mt-1">{{ $selectedPrealert->carrier?->name ?: '—' }}</div>
                            </div>

                            <div>
                                <flux:label size="xs" class="uppercase text-zinc-400">{{ __('Destination') }}</flux:label>
                                <div class="text-sm font-medium mt-1">
                                    @if($selectedPrealert->destinationPort)
                                        {{ $selectedPrealert->destinationPort->name }}
                                        <span
                                            class="text-zinc-500">({{ $selectedPrealert->destinationPort->state?->code }})</span>
                                    @else
                                        —
                                    @endif
                                </div>
                            </div>
                        </div>
                    </x-crud.panel>
                </div>

                {{-- Bottom: Vehicles List --}}
                <div class="space-y-4">
                    <flux:heading size="sm" class="uppercase tracking-wider text-zinc-500">
                        {{ __('Vehicles & Documentation') }}</flux:heading>

                    <div class="space-y-4 overflow-y-auto max-h-[600px] pr-2">
                        @foreach($selectedPrealert->vehicles as $vehicle)
                            <div
                                class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden shadow-sm">
                                <div class="flex flex-col sm:flex-row">
                                    {{-- Image or Icon --}}
                                    <div class="sm:w-40 sm:h-auto h-32 bg-zinc-100 dark:bg-zinc-800 shrink-0">
                                        @php $photos = $vehicle->copartCarPhotoUrls(); @endphp
                                        @if(count($photos) > 0)
                                            <img src="{{ $photos[0] }}" class="w-full h-full object-cover">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center text-zinc-400">
                                                <flux:icon.photo class="size-8" />
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Details --}}
                                    <div class="flex-1 p-4">
                                        <div class="flex justify-between items-start mb-2">
                                            <div>
                                                <h4 class="font-bold text-zinc-900 dark:text-white">{{ $vehicle->year }}
                                                    {{ $vehicle->make }} {{ $vehicle->model }}</h4>
                                                <div class="flex items-center gap-3 mt-1">
                                                    <span
                                                        class="font-mono text-xs text-zinc-500 uppercase">{{ $vehicle->vin }}</span>
                                                    <span class="text-[10px] text-zinc-400">LOT:
                                                        {{ $vehicle->lot_number ?: '—' }}</span>
                                                </div>
                                            </div>
                                            @if($vehicle->gatepass_pin)
                                                <div class="text-right">
                                                    <flux:label size="xs" class="uppercase text-zinc-400">{{ __('Gatepass PIN') }}
                                                    </flux:label>
                                                    <div class="font-mono text-xs font-bold text-zinc-900 dark:text-white">
                                                        {{ $vehicle->gatepass_pin }}</div>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="grid grid-cols-2 gap-4 mt-4 text-xs">
                                            <div>
                                                <span
                                                    class="text-zinc-400 uppercase tracking-tighter">{{ __('Auction') }}:</span>
                                                <span
                                                    class="text-zinc-600 dark:text-zinc-300 font-medium">{{ $vehicle->auction_name }}</span>
                                            </div>
                                            <div>
                                                <span
                                                    class="text-zinc-400 uppercase tracking-tighter">{{ __('Location') }}:</span>
                                                <span class="text-zinc-600 dark:text-zinc-300 font-medium whitespace-normal break-words block"
                                                    title="{{ $vehicle->location }}">{{ $vehicle->location }}</span>
                                            </div>
                                        </div>

                                        {{-- Receipt --}}
                                        @if($vehicle->auction_receipt)
                                            <div
                                                class="mt-4 pt-4 border-t border-zinc-100 dark:border-zinc-800 flex items-center justify-between">
                                                <div class="flex items-center gap-2">
                                                    <flux:icon.document-text class="size-4 text-zinc-400" />
                                                    <span
                                                        class="text-xs font-medium text-zinc-500">{{ __('Auction Receipt Uploaded') }}</span>
                                                </div>
                                                <flux:link :href="Storage::url($vehicle->auction_receipt)" target="_blank" size="xs"
                                                    icon="external-link">
                                                    {{ __('View') }}
                                                </flux:link>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="lg:col-span-3 mt-4 flex justify-end gap-3 pt-6 border-t border-zinc-100 dark:border-zinc-800">
                    @if (auth()->user()?->hasRole('super_admin') || auth()->user()?->staff()->exists())
                        <flux:button variant="primary" icon="car-front" wire:click="convertToShipment({{ $selectedPrealert->id }})">
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