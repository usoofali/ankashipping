<?php

declare(strict_types=1);

use App\Enums\ShipmentStatus;
use App\Enums\ShippingMode;
use App\Enums\VehicleStatus;
use App\Models\ActivityLog;
use App\Models\Carrier;
use App\Models\Consignee;
use App\Models\Port;
use App\Models\Shipment;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use WireUi\Traits\WireUiActions;

new #[Title('Edit Shipment')] class extends Component {
    use WireUiActions;

    public Shipment $shipment;

    public string $reference_no = '';

    public ?int $shipper_id = null;

    public ?int $consignee_id = null;

    public ?int $notify_party_id = null;

    public ?int $carrier_id = null;

    public ?int $origin_port_id = null;

    public ?int $destination_port_id = null;

    public string $logistics_service = '';

    public string $shipping_mode = '';

    public string $shipment_status = '';

    public string $invoice_status = '';

    public string $payment_status = '';

    public ?int $payment_method_id = null;

    public int $capacity = 1;

    public ?string $sealed_at = null;

    public array $vehicles = [];

    public ?int $vehicleIdToRemove = null;

    public function mount(Shipment $shipment): void
    {
        $this->authorize('shipments.update');

        $this->shipment = $shipment->load([
            'shipper.user',
            'consignee',
            'notifyParty',
            'carrier',
            'originPort.state',
            'originPort.country',
            'destinationPort.state',
            'destinationPort.country',
            'invoice',
        ]);

        $this->reference_no = (string) $shipment->reference_no;
        $this->shipper_id = $shipment->shipper_id;
        $this->consignee_id = $shipment->consignee_id;
        $this->notify_party_id = $shipment->notify_party_id;
        $this->carrier_id = $shipment->carrier_id;
        $this->origin_port_id = $shipment->origin_port_id;
        $this->destination_port_id = $shipment->destination_port_id;
        $this->logistics_service = (string) ($shipment->logistics_service?->value ?? $shipment->logistics_service ?? '');
        $this->shipping_mode = (string) ($shipment->shipping_mode?->value ?? $shipment->shipping_mode ?? '');
        $this->shipment_status = (string) ($shipment->shipment_status?->value ?? $shipment->shipment_status ?? '');
        $this->invoice_status = (string) ($shipment->invoice_status?->value ?? $shipment->invoice_status ?? '');
        $this->payment_status = (string) ($shipment->payment_status?->value ?? $shipment->payment_status ?? '');
        $this->payment_method_id = $shipment->payment_method_id;
        $this->capacity = $shipment->capacity ?? 1;
        $this->sealed_at = $shipment->sealed_at ? $shipment->sealed_at->toDateTimeString() : null;

        $this->vehicles = $shipment->vehicles->map(fn($v) => [
            'id' => $v->id,
            'vin' => $v->vin,
            'details' => $v,
        ])->toArray();
    }

    public function removeVehicle(int $vehicleId): void
    {
        $this->vehicles = array_filter($this->vehicles, fn($v) => $v['id'] !== $vehicleId);

        if (count($this->vehicles) === 0) {
            $this->notification()->warning(__('Warning: Shipment will not be savable without at least 1 vehicle.'));
        } elseif (count($this->vehicles) === 1 && $this->shipping_mode === ShippingMode::Container->value) {
            $this->notification()->info(__('You can now change the shipping mode to RoRo if needed.'));
        }
    }

    public function confirmRemove(int $id): void
    {
        $this->vehicleIdToRemove = $id;
        \Flux::modal('remove-vehicle-modal')->show();
    }

    public function executeRemove(): void
    {
        if ($this->vehicleIdToRemove) {
            $this->removeVehicle($this->vehicleIdToRemove);
            \Flux::modal('remove-vehicle-modal')->close();
            $this->vehicleIdToRemove = null;
        }
    }

    public function updatedShippingMode(string $value): void
    {
        if ($value === ShippingMode::Container->value) {
            $this->capacity = 5;
            $this->shipment_status = ShipmentStatus::Open->value;
        } else {
            $this->capacity = 1;
        }
    }

    public function save(): void
    {
        $this->authorize('shipments.update');

        $validated = $this->validate([
            'reference_no' => ['required', 'string', 'max:255', 'unique:shipments,reference_no,' . $this->shipment->id],
            'shipper_id' => ['required', 'exists:shippers,id'],
            'consignee_id' => ['required', 'exists:consignees,id'],
            'notify_party_id' => ['nullable', 'exists:consignees,id'],
            'carrier_id' => ['nullable', 'exists:carriers,id'],
            'origin_port_id' => ['nullable', 'exists:ports,id'],
            'destination_port_id' => ['nullable', 'exists:ports,id'],
            'logistics_service' => ['required', 'string'],
            'shipping_mode' => ['required', 'string'],
            'shipment_status' => ['required', 'string'],
            'invoice_status' => ['required', 'string'],
            'payment_status' => ['required', 'string'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'capacity' => ['required', 'integer', 'min:1'],
            'vehicles' => ['required', 'array', 'min:1'],
        ]);

        if ($this->shipping_mode === ShippingMode::Roro->value && count($this->vehicles) > 1) {
            $this->notification()->error(__('RORO shipments can only have one vehicle.'));

            return;
        }

        $updateData = collect($validated)->except('vehicles')->merge([
            'sealed_at' => $this->sealed_at,
            'notify_party_id' => $this->notify_party_id,
        ])->toArray();

        $this->shipment->update($updateData);

        if ($this->shipment->invoice) {
            $this->shipment->invoice->update([
                'status' => $this->invoice_status,
            ]);
        }

        $currentVehicleIds = array_column($this->vehicles, 'id');
        $originalVehicleIds = $this->shipment->vehicles->pluck('id')->toArray();
        $removedVehicleIds = array_diff($originalVehicleIds, $currentVehicleIds);

        if (!empty($removedVehicleIds)) {
            Vehicle::whereIn('id', $removedVehicleIds)->update([
                'shipment_id' => null,
                'tracking_status' => VehicleStatus::Pending->value,
            ]);

            ActivityLog::create([
                'shipment_id' => $this->shipment->id,
                'user_id' => Auth::id(),
                'action' => 'updated',
                'properties' => [
                    'message' => __('Removed vehicles from shipment: ') . implode(', ', $removedVehicleIds),
                    'source' => 'shipment_edit',
                ],
            ]);
        }

        ActivityLog::create([
            'shipment_id' => $this->shipment->id,
            'user_id' => Auth::id(),
            'action' => 'updated',
            'properties' => [
                'message' => __('Shipment updated from edit page.'),
                'source' => 'shipment_edit',
            ],
        ]);

        $this->notification()->success(__('Shipment updated successfully.'));
        $this->redirect(route('shipments.show', $this->shipment), navigate: true);
    }

    #[Computed]
    public function consignees()
    {
        if (!$this->shipper_id) {
            return collect();
        }

        return Consignee::query()
            ->where('shipper_id', $this->shipper_id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function shipmentDestinationPorts()
    {
        return Port::query()
            ->where('type', 'destination')
            ->with(['state', 'country'])
            ->orderBy('name')
            ->get()
            ->map(function (Port $port): Port {
                $port->name = sprintf(
                    '%s (%s - %s)',
                    $port->name,
                    $port->state?->code ?? '—',
                    $port->country?->iso2 ?? '—'
                );

                return $port;
            });
    }

    #[Computed]
    public function shipmentOriginPorts()
    {
        return Port::query()
            ->where('type', 'origin')
            ->with(['state', 'country'])
            ->orderBy('name')
            ->get()
            ->map(function (Port $port): Port {
                $port->name = sprintf(
                    '%s (%s - %s)',
                    $port->name,
                    $port->state?->code ?? '—',
                    $port->country?->iso2 ?? '—'
                );

                return $port;
            });
    }

    #[Computed]
    public function originPort(): ?Port
    {
        if ($this->origin_port_id === null) {
            return null;
        }

        return Port::query()
            ->with(['state', 'country'])
            ->find($this->origin_port_id);
    }

    #[Computed]
    public function destinationPort(): ?Port
    {
        if ($this->destination_port_id === null) {
            return null;
        }

        return Port::query()
            ->with(['state', 'country'])
            ->find($this->destination_port_id);
    }

    #[Computed]
    public function carriers()
    {
        return Carrier::query()
            ->orderBy('name')
            ->get();
    }
}; ?>

<x-crud.page-shell>
    <div class="space-y-6">
        <div class="flex items-center gap-3">
            <flux:button variant="ghost" icon="arrow-left" :href="route('shipments.show', $shipment)" wire:navigate />
            <x-crud.page-header :heading="__('Edit Shipment')" :subheading="__('Update shipment routing, statuses, and assignment details.')" class="!mb-0" />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Reference Card --}}
            <x-crud.panel
                class="p-6 border-indigo-100 dark:border-indigo-900/40 bg-linear-to-br from-indigo-50/50 to-white dark:from-indigo-900/10 dark:to-zinc-900 flex flex-col justify-center">
                <flux:text size="xs" class="uppercase tracking-widest font-bold text-indigo-500 mb-1">
                    {{ __('Active Shipment Reference') }}
                </flux:text>
                <div class="flex items-center gap-3">
                    <div
                        class="rounded-lg bg-indigo-100 dark:bg-indigo-900/50 p-3 text-indigo-600 dark:text-indigo-400">
                        <flux:icon.qr-code class="size-8" />
                    </div>
                    <div>
                        <flux:heading size="xl" class="font-mono! tracking-tighter">{{ $reference_no }}</flux:heading>
                        <flux:text size="sm" class="text-zinc-500">{{ __('Edit Mode') }}</flux:text>
                    </div>
                </div>
            </x-crud.panel>

            {{-- Shipper Profile Card --}}
            @if($shipment->shipper)
                <x-crud.panel class="lg:col-span-2 p-6 flex flex-col justify-center">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <flux:avatar :name="$shipment->shipper->user->name" size="lg"
                                class="bg-indigo-100! text-indigo-700!" />
                            <div>
                                <flux:heading size="lg">{{ $shipment->shipper->company_name }}</flux:heading>
                                <div class="flex flex-wrap gap-x-4 gap-y-1 mt-1">
                                    <div class="flex items-center gap-2 text-zinc-500">
                                        <flux:icon.user class="size-3.5" />
                                        <flux:text size="sm">{{ $shipment->shipper->user->name }}</flux:text>
                                    </div>
                                    <div class="flex items-center gap-2 text-zinc-500">
                                        <flux:icon.envelope class="size-3.5" />
                                        <flux:text size="sm">{{ $shipment->shipper->user->email }}</flux:text>
                                    </div>
                                    @if($shipment->shipper->phone)
                                        <div class="flex items-center gap-2 text-zinc-500">
                                            <flux:icon.phone class="size-3.5" />
                                            <flux:text size="sm">{{ $shipment->shipper->phone }}</flux:text>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="hidden md:block">
                            <flux:badge color="indigo" variant="subtle" size="sm" icon="shield-check">
                                {{ __('Verified Shipper') }}
                            </flux:badge>
                        </div>
                    </div>
                </x-crud.panel>
            @endif
        </div>

        {{-- Vehicles List --}}
        <div class="grid grid-cols-1 gap-6">
            <x-crud.panel class="p-6">
                <flux:heading size="lg" class="mb-4 flex items-center gap-2">
                    <flux:icon.car-front class="size-5 text-indigo-500" />
                    {{ __('Vehicles to Ship') }} ({{ count($vehicles) }})
                </flux:heading>

                <div class="space-y-4">
                    @foreach($vehicles as $index => $v)
                        <div wire:key="ev-{{ $v['id'] }}"
                            class="relative group bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all">

                            {{-- Remove Vehicle Button overlay --}}
                            <div class="absolute top-3 right-3 z-20">
                                <flux:button variant="danger" size="sm" icon="trash"
                                    wire:click="confirmRemove({{ $v['id'] }})">
                                    {{ __('Remove') }}
                                </flux:button>
                            </div>

                            <div class="flex flex-col lg:flex-row">
                                <div class="lg:w-[45%] w-full bg-zinc-100 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700 relative min-h-[300px] lg:min-h-full"
                                    x-data="{ activeSlide: 0, slides: @js($v['details']->copartCarPhotoUrls() ?? []) }">

                                    <template x-for="(slide, index) in slides" :key="index">
                                        <div x-show="activeSlide === index" x-transition.opacity.duration.300ms
                                            class="absolute inset-0">
                                            <img :src="slide" class="w-full h-full object-cover">
                                        </div>
                                    </template>

                                    <button type="button" x-show="slides.length > 1"
                                        @click="activeSlide = activeSlide === 0 ? slides.length - 1 : activeSlide - 1"
                                        class="absolute left-2 top-1/2 -translate-y-1/2 bg-black/40 hover:bg-black/70 text-white rounded-full p-1.5 backdrop-blur-sm transition z-10">
                                        <flux:icon.chevron-left class="size-5" />
                                    </button>

                                    <button type="button" x-show="slides.length > 1"
                                        @click="activeSlide = activeSlide === slides.length - 1 ? 0 : activeSlide + 1"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 bg-black/40 hover:bg-black/70 text-white rounded-full p-1.5 backdrop-blur-sm transition z-10">
                                        <flux:icon.chevron-right class="size-5" />
                                    </button>

                                    <div x-show="slides.length > 1"
                                        class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1.5 z-10">
                                        <template x-for="(slide, index) in slides" :key="'dot-'+index">
                                            <button type="button" @click="activeSlide = index"
                                                :class="activeSlide === index ? 'bg-white scale-110' : 'bg-white/50'"
                                                class="w-2 h-2 rounded-full transition-all shadow-sm"></button>
                                        </template>
                                    </div>

                                    <div x-show="slides.length === 0"
                                        class="absolute inset-0 flex flex-col items-center justify-center text-zinc-400">
                                        <flux:icon.photo class="size-12 mb-2 opacity-50" />
                                        <span class="font-medium text-sm">{{ __('No photos available') }}</span>
                                    </div>
                                </div>

                                <div class="lg:w-[55%] w-full p-4 lg:p-5 flex flex-col">
                                    <div class="flex items-start justify-between mb-4">
                                        <div class="pr-24">
                                            <h4 class="font-bold text-xl text-zinc-900 dark:text-white leading-tight">
                                                {{ $v['details']['year'] ?? '' }} {{ $v['details']['make'] ?? '' }}
                                                {{ $v['details']['model'] ?? '' }}
                                            </h4>
                                            <p class="text-[8px] text-zinc-500 mt-1 uppercase tracking-widest font-bold">
                                                {{ __('Vehicle Identification (VIN)') }}
                                            </p>
                                            <p
                                                class="font-mono text-zinc-700 dark:text-zinc-300 font-medium text-sm mt-0.5">
                                                {{ $v['vin'] }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-y-4 gap-x-4 text-xs mb-5">
                                        <div>
                                            <p class="text-[10px] text-zinc-500 font-bold uppercase tracking-widest mb-1">
                                                {{ __('Color') }}
                                            </p>
                                            <p
                                                class="font-medium text-zinc-900 dark:text-zinc-100 flex items-center gap-1.5">
                                                @if($v['details']['color'] ?? null)
                                                    <span
                                                        class="w-2.5 h-2.5 rounded-full border border-zinc-300 dark:border-zinc-600 shadow-sm"
                                                        style="background-color: {{ strtolower($v['details']['color'] ?? '') === 'charcoal' ? '#36454F' : (strtolower($v['details']['color'] ?? '') === 'grey' || strtolower($v['details']['color'] ?? '') === 'gray' ? '#808080' : (strtolower($v['details']['color'] ?? '') === 'black' ? '#000000' : (strtolower($v['details']['color'] ?? '') === 'white' ? '#FFFFFF' : (strtolower($v['details']['color'] ?? '') === 'silver' ? '#C0C0C0' : (strtolower($v['details']['color'] ?? '') === 'red' ? '#FF0000' : (strtolower($v['details']['color'] ?? '') === 'blue' ? '#0000FF' : 'transparent')))))) }};"></span>
                                                @endif
                                                {{ ($v['details']['color'] ?? null) ?: '—' }}
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] text-zinc-500 font-bold uppercase tracking-widest mb-1">
                                                {{ __('Car Keys') }}
                                            </p>
                                            <p class="font-medium text-zinc-900 dark:text-zinc-100">
                                                @if(($v['details']['car_keys'] ?? null) === '1')
                                                    <span class="text-green-600 dark:text-green-400 flex items-center gap-1">
                                                        <flux:icon.key class="size-3.5" /> {{ __('Yes') }}
                                                    </span>
                                                @elseif(($v['details']['car_keys'] ?? null) === '0')
                                                    <span class="text-red-500 flex items-center gap-1"><flux:icon.x-mark
                                                            class="size-3.5" /> {{ __('No') }}</span>
                                                @else
                                                    —
                                                @endif
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] text-zinc-500 font-bold uppercase tracking-widest mb-1">
                                                {{ __('Location') }}
                                            </p>
                                            <p
                                                class="font-medium text-zinc-900 dark:text-zinc-100 text-sm whitespace-normal break-words">
                                                {{ ($v['details']['location'] ?? null) ?: '—' }}
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] text-zinc-500 font-bold uppercase tracking-widest mb-1">
                                                {{ __('Auction') }}
                                            </p>
                                            <p
                                                class="font-medium text-zinc-900 dark:text-zinc-100 text-sm whitespace-normal break-words">
                                                {{ ($v['details']['auction_name'] ?? null) ?: '—' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-crud.panel>
        </div>

        <form wire:submit="save" class="space-y-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                {{-- Left Column: Editable Shipping Info --}}
                <div class="lg:col-span-2 space-y-6">
                    <x-crud.panel class="p-6">
                        <flux:heading size="lg" class="mb-4">{{ __('Consignee Assignments') }}</flux:heading>

                        <div class="grid grid-cols-1 gap-6">
                            <flux:field>
                                <flux:label class="mb-2">{{ __('Consignee') }}</flux:label>
                                <div class="flex items-start gap-2">
                                    <div class="flex-1">
                                        <flux:select wire:model="consignee_id" :placeholder="__('Select consignee')">
                                            @foreach($this->consignees as $consignee)
                                                <flux:select.option :value="$consignee->id">
                                                    {{ $consignee->name }}
                                                    @if($consignee->is_default)
                                                        <span
                                                            class="ml-2 text-[10px] font-bold uppercase tracking-widest text-zinc-400">
                                                            ({{ __('Default') }})
                                                        </span>
                                                    @endif
                                                </flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    </div>
                                </div>
                                <flux:error name="consignee_id" />
                            </flux:field>

                            <flux:select wire:model="notify_party_id"
                                :label="__('Notify Party / Intermediate Consignee (Optional)')"
                                :placeholder="__('Same as Consignee')">
                                <flux:select.option value="">{{ __('Same as Consignee') }}</flux:select.option>
                                @foreach($this->consignees as $consignee)
                                    <flux:select.option :value="$consignee->id">
                                        {{ $consignee->name }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    </x-crud.panel>

                    <x-crud.panel class="p-6">
                        <flux:heading size="lg" class="mb-4">{{ __('Routes & Logistics') }}</flux:heading>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <flux:select wire:model="origin_port_id" label="{{ __('Origin Port') }}" icon="map-pin">
                                <flux:select.option value="">{{ __('Select Port') }}</flux:select.option>
                                @foreach ($this->shipmentOriginPorts as $port)
                                    <flux:select.option value="{{ $port->id }}">
                                        {{ $port->name }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model="destination_port_id" label="{{ __('Destination Port') }}"
                                icon="flag">
                                <flux:select.option value="">{{ __('Select Port') }}</flux:select.option>
                                @foreach ($this->shipmentDestinationPorts as $port)
                                    <flux:select.option value="{{ $port->id }}">
                                        {{ $port->name }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model="logistics_service" label="{{ __('Service Type') }}"
                                icon="briefcase">
                                @foreach(\App\Enums\LogisticsService::cases() as $service)
                                    <flux:select.option value="{{ $service->value }}">{{ $service->name }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:field>
                                <flux:label class="mb-2">{{ __('Shipping Mode') }}</flux:label>
                                @if(count($vehicles) <= 1)
                                    <flux:radio.group wire:model.live="shipping_mode" variant="segmented" class="w-full!">
                                        <flux:radio :label="__('RoRo')" value="roro" icon="car-front" />
                                        <flux:radio :label="__('Container')" value="container" icon="container" />
                                    </flux:radio.group>
                                @else
                                    <div
                                        class="flex items-center gap-2 p-3 bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700 rounded-xl">
                                        <flux:icon.container class="size-5 text-indigo-500" />
                                        <flux:text font="medium">{{ __('Container (Locked)') }}</flux:text>
                                        <flux:badge size="xs" color="indigo" variant="subtle" class="ml-auto">
                                            {{ count($vehicles) }} {{ __('Vehicles') }}</flux:badge>
                                    </div>
                                @endif
                                <flux:error name="shipping_mode" />
                            </flux:field>

                            <flux:select wire:model="carrier_id" label="{{ __('Default Carrier') }}"
                                icon="building-office">
                                <flux:select.option value="">{{ __('Select Carrier') }}</flux:select.option>
                                @foreach($this->carriers as $carrier)
                                    <flux:select.option value="{{ $carrier->id }}">{{ $carrier->name }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            @if($shipping_mode === \App\Enums\ShippingMode::Container->value)
                                <flux:input wire:model="capacity" label="{{ __('Container Capacity') }}" type="number"
                                    icon="hashtag" />

                                <div class="flex items-center gap-4 pt-6">
                                    <flux:text size="sm" class="font-bold">{{ __('Seal Container') }}</flux:text>
                                    <flux:button type="button" wire:click="$toggle('sealed_at')"
                                        :variant="$sealed_at ? 'primary' : 'ghost'" size="sm">
                                        {{ $sealed_at ? __('Sealed') : __('Mark as Sealed') }}
                                    </flux:button>
                                </div>
                            @endif
                        </div>
                    </x-crud.panel>
                </div>

                {{-- Right Column: Status & Preview --}}
                <div class="space-y-6">
                    <x-crud.panel
                        class="p-6 bg-zinc-50 dark:bg-zinc-800/50 border-zinc-200 dark:border-zinc-700 shadow-sm!">
                        <flux:heading size="lg" class="mb-4">{{ __('Workflow Status') }}</flux:heading>
                        <div class="space-y-4">
                            <flux:select wire:model="shipment_status" label="{{ __('Initial status') }}" icon="clock">
                                @foreach(\App\Enums\ShipmentStatus::cases() as $status)
                                    <flux:select.option value="{{ $status->value }}">{{ $status->name }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:select wire:model="invoice_status" label="{{ __('Invoice Status') }}"
                                icon="document-text">
                                @foreach(\App\Enums\InvoiceStatus::cases() as $status)
                                    <flux:select.option value="{{ $status->value }}">{{ $status->name }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:select wire:model="payment_status" label="{{ __('Payment Status') }}"
                                icon="banknotes">
                                @foreach(\App\Enums\PaymentStatus::cases() as $status)
                                    <flux:select.option value="{{ $status->value }}">{{ $status->name }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:select wire:model="payment_method_id" :label="__('Payment method')" icon="banknotes">
                                <flux:select.option value="">{{ __('None') }}</flux:select.option>
                                @foreach(\App\Models\PaymentMethod::query()->orderBy('name')->get() as $method)
                                    <flux:select.option value="{{ $method->id }}">{{ $method->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    </x-crud.panel>

                    <div class="flex flex-col gap-3">
                        <flux:button variant="primary" type="submit" class="w-full">
                            {{ __('Save Changes') }}
                        </flux:button>
                        <flux:button variant="ghost" :href="route('shipments.show', $shipment)" wire:navigate
                            class="w-full">
                            {{ __('Cancel') }}
                        </flux:button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    {{-- Remove Vehicle Confirmation Modal --}}
    <flux:modal name="remove-vehicle-modal" class="md:w-96">
        <form wire:submit="executeRemove">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Remove Vehicle') }}</flux:heading>
                    <flux:subheading>
                        <p>{{ __('Are you sure you want to remove this vehicle from the shipment?') }}</p>
                        <p class="mt-1">{{ __('The vehicle will be detached when you save your changes.') }}</p>
                    </flux:subheading>
                </div>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>

                    <flux:button type="submit" variant="danger">{{ __('Remove Vehicle') }}</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>
</x-crud.page-shell>