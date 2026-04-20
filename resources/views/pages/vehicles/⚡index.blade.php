<?php

declare(strict_types=1);

use App\Models\Vehicle;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use WireUi\Traits\WireUiActions;

new #[Title('Vehicles')] class extends Component {
    use WireUiActions;
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'f')]
    public string $filter = 'all';

    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public bool $showDeleteModal = false;
    public ?int $editingVehicleId = null;
    public ?int $vehiclePendingDeleteId = null;
    public string $vehiclePendingDeleteLabel = '';

    // Fields
    public string $vin = '';
    public string $lot_number = '';
    public string $make = '';
    public string $model = '';
    public string $year = '';
    public string $series = '';
    public string $body_style = '';
    public string $color = '';
    public string $vehicle_type = '';
    public string $transmission = '';
    public string $fuel = '';
    public string $engine_type = '';
    public string $drive = '';
    public ?int $cylinders = null;
    public ?int $odometer = null;
    public string $car_keys = '';
    public string $doc_type = '';
    public string $auction_name = '';
    public string $seller = '';
    public ?float $est_retail_value = null;

    protected function rules(): array
    {
        return [
            'vin' => 'required|string|max:50|unique:vehicles,vin' . ($this->editingVehicleId ? ',' . $this->editingVehicleId : ''),
            'lot_number' => 'nullable|string|max:50',
            'make' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'year' => 'nullable|string|max:4',
            'series' => 'nullable|string|max:100',
            'body_style' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:50',
            'vehicle_type' => 'nullable|string|max:100',
            'transmission' => 'nullable|string|max:50',
            'fuel' => 'nullable|string|max:50',
            'engine_type' => 'nullable|string|max:100',
            'drive' => 'nullable|string|max:50',
            'cylinders' => 'nullable|integer|min:0',
            'odometer' => 'nullable|integer|min:0',
            'car_keys' => 'nullable|string|max:50',
            'doc_type' => 'nullable|string|max:255',
            'auction_name' => 'nullable|string|max:255',
            'seller' => 'nullable|string|max:255',
            'est_retail_value' => 'nullable|numeric|min:0',
        ];
    }

    public function mount(): void
    {
        $this->authorize('vehicles.view');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function isShipper(): bool
    {
        return auth()->user()->shipper()->exists();
    }

    #[Computed]
    public function vehicles()
    {
        $isShipper = $this->isShipper;
        $shipperId = auth()->user()->shipper?->id;

        return Vehicle::query()
            ->with(['shipment.shipper', 'prealert.shipper'])
            // Visibility scoping
            ->when($isShipper, function ($q) use ($shipperId) {
                $q->where(function ($sq) use ($shipperId) {
                    $sq->whereHas('shipment', fn($sqq) => $sqq->where('shipper_id', $shipperId))
                        ->orWhereHas('prealert', fn($sqq) => $sqq->where('shipper_id', $shipperId));
                });
            })
            // Filter scoping
            ->when($this->filter === 'shipment', fn($q) => $q->whereNotNull('shipment_id'))
            ->when($this->filter === 'prealert', fn($q) => $q->whereNotNull('prealert_id'))
            ->when($this->filter === 'none', fn($q) => $q->whereNull('shipment_id')->whereNull('prealert_id'))
            // Search
            ->when($this->search, fn($q) => $q->where(fn($sq) => $sq->where('vin', 'like', "%{$this->search}%")
                ->orWhere('make', 'like', "%{$this->search}%")
                ->orWhere('model', 'like', "%{$this->search}%")
                ->orWhere('lot_number', 'like', "%{$this->search}%")))
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    public function openCreateModal(): void
    {
        $this->authorize('vehicles.create');
        $this->reset(['vin', 'lot_number', 'make', 'model', 'year', 'series', 'body_style', 'color', 'vehicle_type', 'transmission', 'fuel', 'engine_type', 'drive', 'cylinders', 'odometer', 'car_keys', 'doc_type', 'auction_name', 'seller', 'est_retail_value', 'editingVehicleId']);
        $this->showCreateModal = true;
    }

    public function saveNewVehicle(): void
    {
        $this->authorize('vehicles.create');
        $validated = $this->validate();

        $defaultPhoto = 'https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?q=80&w=300&auto=format&fit=crop';

        $validated['api_snapshot'] = [
            'provider' => 'manual-entry',
            'car_photo' => ['photo' => [$defaultPhoto]],
            'result_item' => $validated,
        ];
        $validated['api_fetched_at'] = now();

        Vehicle::create($validated);

        $this->showCreateModal = false;
        $this->notification()->success(__('Vehicle created successfully.'));
    }

    public function openEditModal(int $id): void
    {
        $this->authorize('vehicles.update');
        $vehicle = Vehicle::findOrFail($id);

        $this->editingVehicleId = $vehicle->id;
        $this->vin = $vehicle->vin ?? '';
        $this->lot_number = $vehicle->lot_number ?? '';
        $this->make = $vehicle->make ?? '';
        $this->model = $vehicle->model ?? '';
        $this->year = $vehicle->year ?? '';
        $this->series = $vehicle->series ?? '';
        $this->body_style = $vehicle->body_style ?? '';
        $this->color = $vehicle->color ?? '';
        $this->vehicle_type = $vehicle->vehicle_type ?? '';
        $this->transmission = $vehicle->transmission ?? '';
        $this->fuel = $vehicle->fuel ?? '';
        $this->engine_type = $vehicle->engine_type ?? '';
        $this->drive = $vehicle->drive ?? '';
        $this->cylinders = $vehicle->cylinders;
        $this->odometer = $vehicle->odometer;
        $this->car_keys = $vehicle->car_keys ?? '';
        $this->doc_type = $vehicle->doc_type ?? '';
        $this->auction_name = $vehicle->auction_name ?? '';
        $this->seller = $vehicle->seller ?? '';
        $this->est_retail_value = (float) $vehicle->est_retail_value;

        $this->showEditModal = true;
    }

    public function saveVehicle(): void
    {
        $this->authorize('vehicles.update');
        $validated = $this->validate();

        $vehicle = Vehicle::findOrFail($this->editingVehicleId);

        // If it was manual or is being updated, maybe we keep the snapshot but update attributes
        if (($vehicle->api_snapshot['provider'] ?? 'unknown') === 'manual-entry') {
            $snap = $vehicle->api_snapshot;
            $snap['result_item'] = $validated;
            $validated['api_snapshot'] = $snap;
        }

        $vehicle->update($validated);

        $this->showEditModal = false;
        $this->notification()->success(__('Vehicle updated successfully.'));
    }

    public function openDeleteModal(int $id): void
    {
        $this->authorize('vehicles.delete');
        $vehicle = Vehicle::findOrFail($id);
        $this->vehiclePendingDeleteId = $vehicle->id;
        $this->vehiclePendingDeleteLabel = ($vehicle->year ? $vehicle->year . ' ' : '') . ($vehicle->make ?: '') . ' ' . ($vehicle->model ?: '') . ' (' . ($vehicle->vin ?: 'No VIN') . ')';
        $this->showDeleteModal = true;
    }

    public function deleteVehicle(): void
    {
        $this->authorize('vehicles.delete');

        if ($this->vehiclePendingDeleteId) {
            $vehicle = Vehicle::findOrFail($this->vehiclePendingDeleteId);

            if ($vehicle->shipment_id || $vehicle->prealert_id) {
                $this->showDeleteModal = false;
                $this->notification()->warning(__('Cannot delete this vehicle because it is associated with a shipment or prealert.'));
            } else {
                $vehicle->delete();
                $this->showDeleteModal = false;
                $this->notification()->success(__('Vehicle deleted successfully.'));
            }
        }

        $this->vehiclePendingDeleteId = null;
        $this->vehiclePendingDeleteLabel = '';
    }
}; ?>

<div>
    <x-crud.page-shell>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-2 gap-4">
            <x-crud.page-header :heading="__('Vehicles')" :subheading="__('Manage vehicles.')" icon="car-front"
                class="!mb-0" />
            @can('vehicles.create')
                <flux:button variant="primary" icon="plus" wire:click="openCreateModal">{{ __('Create Vehicle') }}
                </flux:button>
            @endcan
        </div>

        <div class="mb-1 flex gap-2">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                :placeholder="__('Search by VIN, Make, Model or Lot...')" clearable class="w-full" />

            <flux:select wire:model.live="filter" class="w-full">
                <flux:select.option value="all">{{ __('All Vehicles') }}</flux:select.option>
                <flux:select.option value="shipment">{{ __('With Shipment') }}</flux:select.option>
                <flux:select.option value="prealert">{{ __('With Prealert') }}</flux:select.option>
                @if(auth()->user()->hasAnyRole(['super_admin', 'staff_admin', 'staff_operator']))
                    <flux:select.option value="none">{{ __('None (Unlinked)') }}</flux:select.option>
                @endif
            </flux:select>
        </div>

        <x-crud.panel class="p-6">
            <flux:table :paginate="$this->vehicles">
                <flux:table.columns sticky class="bg-white dark:bg-zinc-900">
                    @if (!$this->isShipper)
                        <flux:table.column icon="building-office-2">{{ __('Shipper') }}</flux:table.column>
                    @endif
                    <flux:table.column icon="hashtag">{{ __('VIN') }}</flux:table.column>
                    <flux:table.column>{{ __('Preview') }}</flux:table.column>
                    <flux:table.column icon="car-front">{{ __('Vehicle') }}</flux:table.column>
                    <flux:table.column icon="banknotes">{{ __('Est. Value') }}</flux:table.column>
                    <flux:table.column align="right">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->vehicles as $vehicle)
                        <flux:table.row :key="$vehicle->id">
                            @if (!$this->isShipper)
                                <flux:table.cell>
                                    <div class="flex flex-col">
                                        <span class="font-medium text-zinc-900 dark:text-white">
                                            {{ $vehicle->shipment?->shipper?->company_name ?? $vehicle->prealert?->shipper?->company_name ?? '—' }}
                                        </span>
                                        <span class="text-xs text-zinc-500">
                                            {{ $vehicle->shipment?->shipper?->phone ?? $vehicle->prealert?->shipper?->phone ?? '' }}
                                        </span>
                                    </div>
                                </flux:table.cell>
                            @endif
                             <flux:table.cell>
                                <div class="flex flex-col">
                                    <span class="font-mono text-xs">{{ $vehicle->vin ?: '—' }}</span>
                                    <span class="text-xs text-zinc-500">{{ $vehicle->lot_number ?: '' }}</span>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                @php $photo = $vehicle->copartCarPhotoUrls()[0] ?? 'https://placehold.co/100x75?text=No+Photo'; @endphp
                                <img src="{{ $photo }}" alt="Vehicle"
                                    class="h-10 w-16 object-cover rounded shadow-sm border border-zinc-200 dark:border-zinc-700">
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex flex-col">
                                    <span class="font-medium text-zinc-900 dark:text-white">
                                        {{ $vehicle->year ?: '' }} {{ $vehicle->make ?: '' }} {{ $vehicle->model ?: '' }}
                                    </span>
                                    <span class="text-xs text-zinc-500">
                                        {{ $vehicle->color ?: 'No color' }}
                                    </span>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell class="text-sm text-zinc-500">
                                {{ $vehicle->est_retail_value ? number_format((float) $vehicle->est_retail_value, 2) : '—' }}
                            </flux:table.cell>
                            <flux:table.cell align="right">
                                <flux:dropdown align="end" variant="ghost">
                                    <flux:button variant="ghost" icon="ellipsis-horizontal" size="sm" />
                                    <flux:menu>
                                        @can('vehicles.update')
                                            <flux:menu.item icon="pencil-square" wire:click="openEditModal({{ $vehicle->id }})">
                                                {{ __('Edit') }}
                                            </flux:menu.item>
                                        @endcan
                                        @can('vehicles.delete')
                                            @if (!$vehicle->shipment_id && !$vehicle->prealert_id)
                                                <flux:menu.separator />
                                                <flux:menu.item icon="trash" variant="danger"
                                                    wire:click="openDeleteModal({{ $vehicle->id }})">
                                                    {{ __('Delete') }}
                                                </flux:menu.item>
                                            @endif
                                        @endcan
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="8" class="py-8 text-center text-zinc-500">
                                {{ __('No vehicles found.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </x-crud.panel>
    </x-crud.page-shell>

    {{-- Create Modal --}}
    <flux:modal wire:model="showCreateModal" class="md:max-w-4xl">
        <form wire:submit="saveNewVehicle" class="space-y-6">
            <div class="flex items-center gap-3">
                <flux:icon name="car-front" class="text-zinc-500" />
                <div>
                    <flux:heading size="lg">{{ __('Create Vehicle') }}</flux:heading>
                    <flux:subheading>{{ __('Add a new vehicle to the system manually.') }}</flux:subheading>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <flux:input wire:model="vin" :label="__('VIN')" icon="hashtag" required placeholder="ABC123..." />
                <flux:input wire:model="lot_number" :label="__('Lot Number')" icon="tag" placeholder="12345678" />
                <flux:input wire:model="make" :label="__('Make')" icon="building-office" placeholder="Toyota" />
                <flux:input wire:model="model" :label="__('Model')" icon="car-front" placeholder="Camry" />
                <flux:input wire:model="year" :label="__('Year')" icon="calendar" placeholder="2024" />
                <flux:input wire:model="color" :label="__('Color')" icon="paint-brush" placeholder="Black" />

                <flux:input wire:model="series" :label="__('Series')" placeholder="SE" />
                <flux:input wire:model="body_style" :label="__('Body Style')" placeholder="Sedan" />
                <flux:input wire:model="vehicle_type" :label="__('Vehicle Type')" placeholder="Automobile" />

                <flux:input wire:model="transmission" :label="__('Transmission')" placeholder="Automatic" />
                <flux:input wire:model="fuel" :label="__('Fuel')" placeholder="Gas" />
                <flux:input wire:model="engine_type" :label="__('Engine Type')" placeholder="2.5L 4-Cyl" />

                <flux:input wire:model="drive" :label="__('Drive')" placeholder="FWD" />
                <flux:input wire:model="cylinders" :label="__('Cylinders')" type="number" placeholder="4" />
                <flux:input wire:model="odometer" :label="__('Odometer (Mi)')" type="number" placeholder="10" />

                <flux:input wire:model="car_keys" :label="__('Keys Status')" placeholder="1" />
                <flux:input wire:model="doc_type" :label="__('Doc Type')" placeholder="CLEAN TITLE" />
                <flux:input wire:model="auction_name" :label="__('Auction Name')" placeholder="Copart" />

                <flux:input wire:model="seller" :label="__('Seller')" placeholder="Geico" />
                <flux:input wire:model="est_retail_value" :label="__('Est. Retail Value')" type="number" step="0.01"
                    placeholder="25000.00" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Create Vehicle') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit Modal --}}
    <flux:modal wire:model="showEditModal" class="md:max-w-4xl">
        <form wire:submit="saveVehicle" class="space-y-6">
            <div class="flex items-center gap-3">
                <flux:icon name="pencil-square" class="text-zinc-500" />
                <div>
                    <flux:heading size="lg">{{ __('Edit Vehicle') }}</flux:heading>
                    <flux:subheading>{{ __('Update vehicle details.') }}</flux:subheading>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <flux:input wire:model="vin" :label="__('VIN')" icon="hashtag" required />
                <flux:input wire:model="lot_number" :label="__('Lot Number')" icon="tag" />
                <flux:input wire:model="make" :label="__('Make')" icon="building-office" />
                <flux:input wire:model="model" :label="__('Model')" icon="car-front" />
                <flux:input wire:model="year" :label="__('Year')" icon="calendar" />
                <flux:input wire:model="color" :label="__('Color')" icon="paint-brush" />

                <flux:input wire:model="series" :label="__('Series')" />
                <flux:input wire:model="body_style" :label="__('Body Style')" />
                <flux:input wire:model="vehicle_type" :label="__('Vehicle Type')" />

                <flux:input wire:model="transmission" :label="__('Transmission')" />
                <flux:input wire:model="fuel" :label="__('Fuel')" />
                <flux:input wire:model="engine_type" :label="__('Engine Type')" />

                <flux:input wire:model="drive" :label="__('Drive')" />
                <flux:input wire:model="cylinders" :label="__('Cylinders')" type="number" />
                <flux:input wire:model="odometer" :label="__('Odometer (Mi)')" type="number" />

                <flux:input wire:model="car_keys" :label="__('Keys Status')" />
                <flux:input wire:model="doc_type" :label="__('Doc Type')" />
                <flux:input wire:model="auction_name" :label="__('Auction Name')" />

                <flux:input wire:model="seller" :label="__('Seller')" />
                <flux:input wire:model="est_retail_value" :label="__('Est. Retail Value')" type="number" step="0.01" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save Changes') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete Modal --}}
    <flux:modal wire:model="showDeleteModal" class="max-w-md">
        <form wire:submit="deleteVehicle" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete Vehicle') }}</flux:heading>
                <flux:subheading>
                    {{ __('Are you sure you want to delete ":name"? This action cannot be undone.', ['name' => $vehiclePendingDeleteLabel]) }}
                </flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Delete') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>