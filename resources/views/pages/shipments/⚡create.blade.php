<?php

declare(strict_types=1);

use App\Models\Prealert;
use App\Models\Shipment;
use App\Models\DefaultShipmentSetting;
use App\Models\SystemSetting;
use App\Models\Shipper;
use App\Models\User;
use App\Models\Consignee;
use App\Models\Carrier;
use App\Models\PaymentMethod;
use App\Models\Port;
use App\Models\Vehicle;
use App\Models\ShipmentTracking;
use App\Models\Invoice;
use App\Models\ActivityLog;
use App\Enums\ShipmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\LogisticsService;
use App\Enums\ShippingMode;
use App\Enums\VehicleStatus;
use App\Models\VehicleTracking;
use Spatie\Permission\Models\Role;
use App\Notifications\ShipmentCreatedNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use WireUi\Traits\WireUiActions;

new #[Title('Create Shipment')] class extends Component {
    use WireUiActions;

    #[Url]
    public ?int $prealert = null;

    // Form Properties
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
    public string $initial_vehicle_status = '';
    public ?string $notes = '';
    public int $capacity = 1;
    public ?string $sealed_at = null;
    public bool $towing = false;

    public bool $showConsigneeModal = false;
    public string $newConsigneeName = '';
    public string $newConsigneeAddress = '';

    public ?Shipment $targetShipment = null;

    // Multi-vehicle state
    public array $vehicles = []; // items: ['id' => int, 'vin' => string, 'details' => Vehicle]

    // Expanded Context
    public ?Vehicle $selectedVehicle = null;
    public ?Shipper $selectedShipper = null;

    public function mount(): void
    {
        $defaults = DefaultShipmentSetting::current();
        $system = SystemSetting::current();

        // 1. Set Defaults from Singleton
        $this->origin_port_id = $defaults->origin_port_id;
        $this->logistics_service = $defaults->logistics_service->value ?? '';
        $this->shipping_mode = $defaults->shipping_mode->value ?? '';
        $this->shipment_status = $defaults->shipment_status->value ?? ShipmentStatus::Pending->value;
        $this->invoice_status = $defaults->invoice_status->value ?? InvoiceStatus::Draft->value;
        $this->payment_status = $defaults->payment_status->value ?? PaymentStatus::AwaitingBL->value;
        $this->payment_method_id = $defaults->payment_method_id;
        $this->initial_vehicle_status = $defaults->initial_vehicle_status->value ?? VehicleStatus::Pending->value;

        // 2. Override from Prealert if provided
        if ($this->prealert) {
            $pre = Prealert::with('vehicles')->findOrFail($this->prealert);
            $this->shipper_id = $pre->shipper_id;
            $this->consignee_id = $pre->consignee_id;
            $this->notify_party_id = $pre->notify_party_id;
            $this->carrier_id = $pre->carrier_id;
            $this->destination_port_id = $pre->destination_port_id;
            $this->shipping_mode = $pre->shipping_mode?->value ?? $this->shipping_mode;
            $this->notes = $pre->notes;
            $this->towing = $pre->towing;

            if ($this->shipping_mode === ShippingMode::Container->value) {
                $this->capacity = 5;
                $this->shipment_status = ShipmentStatus::Open->value;
            }

            foreach ($pre->vehicles as $v) {
                $this->vehicles[] = [
                    'id' => $v->id,
                    'vin' => $v->vin,
                    'details' => $v,
                ];
            }

            $this->selectedShipper = Shipper::with('user')->find($this->shipper_id);

            // 2.a Check for target shipment
            if ($pre->shipment_id) {
                $this->targetShipment = Shipment::withCount('vehicles')->findOrFail($pre->shipment_id);
                $this->reference_no = $this->targetShipment->reference_no;
                $this->origin_port_id = $this->targetShipment->origin_port_id;
                $this->consignee_id = $this->targetShipment->consignee_id;
                $this->notify_party_id = $this->targetShipment->notify_party_id;
                $this->destination_port_id = $this->targetShipment->destination_port_id;
                $this->logistics_service = $this->targetShipment->logistics_service->value ?? '';
                $this->shipping_mode = $this->targetShipment->shipping_mode->value ?? '';
                $this->carrier_id = $this->targetShipment->carrier_id;
                $this->shipment_status = $this->targetShipment->shipment_status->value ?? '';
                $this->invoice_status = $this->targetShipment->invoice_status->value ?? '';
                $this->payment_status = $this->targetShipment->payment_status->value ?? '';
                $this->payment_method_id = $this->targetShipment->payment_method_id;
                $this->capacity = $this->targetShipment->capacity;
                $this->sealed_at = $this->targetShipment->sealed_at?->toDateTimeString();
            }
        }

        // 3. Generate Reference Number
        $this->generateReferenceNo($system);
    }

    protected function generateReferenceNo(SystemSetting $system): void
    {
        $prefix = $system->tracking_delivery_prefix ?: 'SHP';

        if ($system->tracking_number_type === 'random') {
            $this->reference_no = $prefix . '' . strtoupper(Str::random($system->tracking_random_digits ?: 8));
        } else {
            // Auto Increment Logic
            $lastId = Shipment::withTrashed()->max('id') ?? 0;
            $nextNumber = $lastId + 1;
            $digits = $system->tracking_digits ?: 5;
            $this->reference_no = $prefix . '' . str_pad((string) $nextNumber, $digits, '0', STR_PAD_LEFT);
        }
    }
    public function updatedShippingMode(string $value): void
    {
        if ($value === ShippingMode::Container->value) {
            $this->capacity = 5;
            $this->shipment_status = ShipmentStatus::Open->value;
        } else {
            $this->capacity = 1;
            // Roro default status logic if any
        }
    }

    public function save(): void
    {
        $this->validate([
            'reference_no' => 'required|string|unique:shipments,reference_no',
            'shipper_id' => 'required|exists:shippers,id',
            'consignee_id' => 'required|exists:consignees,id',
            'notify_party_id' => 'nullable|exists:consignees,id',
            'vehicles' => 'required|array|min:1',
            'origin_port_id' => 'nullable|exists:ports,id',
            'destination_port_id' => 'nullable|exists:ports,id',
            'logistics_service' => 'required|string',
            'shipping_mode' => 'required|string',
            'shipment_status' => 'required|string',
            'invoice_status' => 'required|string',
            'payment_status' => 'required|string',
            'payment_method_id' => 'nullable|integer|exists:payment_methods,id',
            'initial_vehicle_status' => 'required|string',
            'capacity' => 'required|integer|min:1',
        ]);

        if ($this->targetShipment) {
            $total = count($this->vehicles) + $this->targetShipment->vehicles()->count();
            if ($total > 5) {
                $this->notification()->error(__('Action impossible: Total vehicles in container would exceed 5.'));

                return;
            }
        }

        if ($this->shipping_mode === ShippingMode::Roro->value && count($this->vehicles) > 1) {
            $this->notification()->error(__('RORO shipments can only have one vehicle.'));
            return;
        }

        try {
            DB::transaction(function () {
                $shipper = Shipper::find($this->shipper_id);
                $isDispatched = !$this->towing;

                if ($this->targetShipment) {
                    $shipment = $this->targetShipment;
                } else {
                    $isShipmentDispatched = $isDispatched && $this->shipping_mode === ShippingMode::Roro->value;
                    $shipmentStatus = $isShipmentDispatched ? ShipmentStatus::Dispatched->value : $this->shipment_status;

                    $shipment = Shipment::create([
                        'reference_no' => $this->reference_no,
                        'shipper_id' => $this->shipper_id,
                        'consignee_id' => $this->consignee_id,
                        'notify_party_id' => $this->notify_party_id,
                        'carrier_id' => $this->carrier_id,
                        'origin_port_id' => $this->origin_port_id,
                        'destination_port_id' => $this->destination_port_id,
                        'logistics_service' => $this->logistics_service,
                        'shipping_mode' => $this->shipping_mode,
                        'shipment_status' => $shipmentStatus,
                        'invoice_status' => $this->invoice_status,
                        'payment_status' => $this->payment_status,
                        'payment_method_id' => $this->payment_method_id,
                        'capacity' => $this->capacity,
                        'sealed_at' => $this->sealed_at,
                        'towing' => $this->towing,
                    ]);

                    // 3. Create Invoice (Only for new shipments)
                    Invoice::create([
                        'shipment_id' => $shipment->id,
                        'invoice_number' => 'INV-' . strtoupper(Str::random(8)),
                        'status' => $this->invoice_status,
                        'subtotal' => 0,
                        'tax_amount' => 0,
                        'total_amount' => 0,
                        'issued_at' => now(),
                    ]);
                }

                // 2. Link Vehicles to Shipment
                foreach ($this->vehicles as $vData) {
                    $vehicle = Vehicle::find($vData['id']);
                    if ($vehicle) {
                        $vehicleStatus = $isDispatched ? VehicleStatus::Dispatched->value : $this->initial_vehicle_status;

                        $updateData = [
                            'shipment_id' => $shipment->id,
                            'tracking_status' => $vehicleStatus,
                        ];

                        if ($isDispatched && $shipper->default_driver_id) {
                            $updateData['driver_id'] = $shipper->default_driver_id;
                        }

                        $vehicle->update($updateData);

                        VehicleTracking::create([
                            'vehicle_id' => $vehicle->id,
                            'status' => $vehicleStatus,
                            'note' => __('Initial tracking status set on shipment creation.'),
                            'metadata' => [
                                'source' => 'shipment_create',
                                'created_by' => Auth::id(),
                                'shipment_id' => $shipment->id,
                            ],
                            'recorded_at' => now(),
                        ]);
                    }
                }

                // 3. Create Initial Tracking
                $finalShipmentStatus = ($this->targetShipment)
                    ? $shipment->shipment_status->value
                    : ($isDispatched && $this->shipping_mode === ShippingMode::Roro->value ? ShipmentStatus::Dispatched->value : $this->shipment_status);

                ShipmentTracking::create([
                    'shipment_id' => $shipment->id,
                    'status' => $finalShipmentStatus,
                    'note' => __('Initial record created.'),
                    'metadata' => [
                        'source' => 'shipment_create',
                        'created_by' => Auth::id(),
                    ],
                    'recorded_at' => now(),
                ]);


                // 4. Create Activity Log
                ActivityLog::create([
                    'shipment_id' => $shipment->id,
                    'user_id' => Auth::id(),
                    'action' => $this->targetShipment ? 'updated' : 'created',
                    'properties' => [
                        'message' => $this->targetShipment
                            ? __('Vehicles added to existing shipment from prealert ID: ') . ($this->prealert ?: 'N/A')
                            : __('Shipment created from prealert ID: ') . ($this->prealert ?: 'N/A'),
                        'source' => 'shipment_create',
                        'prealert_id' => $this->prealert,
                        'is_merge' => (bool) $this->targetShipment,
                    ],
                ]);

                // 5. Delete Prealert after conversion
                if ($this->prealert) {
                    Vehicle::where('prealert_id', $this->prealert)->update(['prealert_id' => null]);
                    Prealert::where('id', $this->prealert)->delete();
                }

                // 6. Send Notifications (non-shipper roles, staff, super_admin, shipper owner)
                $shipment->load('shipper');

                $adminRoleNames = Role::query()
                    ->where('name', '!=', 'shipper')
                    ->pluck('name');

                $recipientIds = User::query()
                    ->role($adminRoleNames)
                    ->pluck('id')
                    ->merge(User::query()->whereHas('staff')->pluck('id'))
                    ->merge(User::query()->whereHas('roles', fn($q) => $q->where('name', 'super_admin'))->pluck('id'))
                    ->when($shipment->shipper?->user_id, fn($q) => $q->push($shipment->shipper->user_id))
                    ->unique()
                    ->values();

                $recipients = User::query()->whereIn('id', $recipientIds)->get();

                if ($recipients->isNotEmpty()) {
                    Notification::send($recipients, new ShipmentCreatedNotification(
                        $shipment,
                        isMerge: (bool) $this->targetShipment,
                        addedCount: count($this->vehicles)
                    ));
                }

                $this->dialog()->show([
                    'icon' => 'success',
                    'title' => __('Success!'),
                    'description' => $this->targetShipment
                        ? __('Vehicles added to existing container successfully.')
                        : ($this->shipping_mode === \App\Enums\ShippingMode::Container->value
                            ? __('Container shipment created successfully.')
                            : __('RoRo shipment created successfully.')),
                    'onClose' => [
                        'method' => 'redirectToShipment',
                        'params' => ['id' => $shipment->id],
                    ],
                ]);
            });
        } catch (\Exception $e) {
            $this->notification()->error(
                title: __('Error'),
                description: $e->getMessage()
            );
        }
    }

    public function redirectToShipment(array $data): void
    {
        $this->redirect(route('shipments.show', $data['id']), navigate: true);
    }

    public function createConsignee(): void
    {
        $this->validate([
            'newConsigneeName' => ['required', 'string', 'max:255'],
            'newConsigneeAddress' => ['nullable', 'string', 'max:500'],
        ]);

        if (!$this->shipper_id) {
            $this->notification()->error(__('Please select a shipper first.'));

            return;
        }

        $consignee = Consignee::create([
            'shipper_id' => $this->shipper_id,
            'name' => $this->newConsigneeName,
            'address' => $this->newConsigneeAddress,
            'is_default' => false,
        ]);

        $this->consignee_id = $consignee->id;
        $this->showConsigneeModal = false;
        $this->reset(['newConsigneeName', 'newConsigneeAddress']);

        $this->notification()->success(__('Consignee created successfully.'));
    }

    #[Computed]
    public function consignees()
    {
        if (!$this->shipper_id) {
            return collect();
        }

        return Consignee::where('shipper_id', $this->shipper_id)
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

    /**
     * Resolved origin port for the current selection (raw name; use state/country for display).
     */
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

    /**
     * Resolved destination port for the current selection.
     */
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
}; ?>

<x-crud.page-shell>
    <div class="space-y-6">
        @if($targetShipment)
            @php
                $totalVehicles = count($vehicles) + $targetShipment->vehicles_count;
            @endphp
            <x-crud.panel class="p-4 border-amber-100 bg-amber-50 dark:bg-amber-950/20 dark:border-amber-900/50">
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <div class="flex items-center gap-4">
                        <div class="p-2 bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-400 rounded-lg">
                            <flux:icon.container class="size-6" />
                        </div>
                        <div>
                            <flux:heading size="sm" class="text-amber-800 dark:text-amber-200">
                                {{ __('Targeting Existing Container:') }} {{ $targetShipment->reference_no }}
                            </flux:heading>
                            <flux:text size="sm" class="text-amber-700 dark:text-amber-300">
                                {{ __('Merging :count new vehicles into this container. (Current occupancy: :current/5)', ['count' => count($vehicles), 'current' => $targetShipment->vehicles_count]) }}
                            </flux:text>
                        </div>
                    </div>
                    @if($totalVehicles > 5)
                        <div class="sm:ml-auto">
                            <flux:badge color="rose" variant="solid" icon="no-symbol" class="animate-pulse">
                                {{ __('Action Impossible: :totalOf5', ['totalOf5' => $totalVehicles . '/5']) }}
                            </flux:badge>
                        </div>
                    @endif
                </div>
            </x-crud.panel>
        @endif

        <div class="space-y-6">
            {{-- Top Section: Reference & Shipper (Read Only) --}}
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
                            <flux:heading size="xl" class="font-mono! tracking-tighter">{{ $reference_no }}
                            </flux:heading>
                            <flux:text size="sm" class="text-zinc-500">{{ __('Initialized from Prealert') }}</flux:text>
                        </div>
                    </div>
                </x-crud.panel>

                {{-- Shipper Profile Card --}}
                @if($selectedShipper)
                    <x-crud.panel class="lg:col-span-2 p-6 flex flex-col justify-center">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <flux:avatar :name="$selectedShipper->user->name" size="lg"
                                    class="bg-indigo-100! text-indigo-700!" />
                                <div>
                                    <flux:heading size="lg">{{ $selectedShipper->company_name }}</flux:heading>
                                    <div class="flex flex-wrap gap-x-4 gap-y-1 mt-1">
                                        <div class="flex items-center gap-2 text-zinc-500">
                                            <flux:icon.user class="size-3.5" />
                                            <flux:text size="sm">{{ $selectedShipper->user->name }}</flux:text>
                                        </div>
                                        <div class="flex items-center gap-2 text-zinc-500">
                                            <flux:icon.envelope class="size-3.5" />
                                            <flux:text size="sm">{{ $selectedShipper->user->email }}</flux:text>
                                        </div>
                                        @if($selectedShipper->phone)
                                            <div class="flex items-center gap-2 text-zinc-500">
                                                <flux:icon.phone class="size-3.5" />
                                                <flux:text size="sm">{{ $selectedShipper->phone }}</flux:text>
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

            {{-- Towing indicator --}}
            <x-crud.panel class="p-4">
                <div class="flex items-center gap-3">
                    <flux:icon.truck class="size-5 text-amber-500 shrink-0" />
                    <div class="flex-1">
                        <flux:heading size="sm">{{ __('Towing') }}</flux:heading>
                        <flux:text size="sm" class="text-zinc-500">{{ __('Is towing required for this shipment?') }}
                        </flux:text>
                    </div>
                    <flux:checkbox wire:model.live="towing" id="shipment-towing" />
                </div>
                @if($towing)
                    <flux:badge color="amber" variant="subtle" icon="truck" size="sm" class="mt-2">
                        {{ __('Towing Required') }}
                    </flux:badge>
                @endif
            </x-crud.panel>

            {{-- 2. Added Vehicles List --}}
            <div class="grid grid-cols-1 gap-6">
                <x-crud.panel class="p-6">
                    <flux:heading size="lg" class="mb-4 flex items-center gap-2">
                        <flux:icon.car-front class="size-5 text-indigo-500" />
                        {{ __('Vehicles to Ship') }} ({{ count($vehicles) }})
                    </flux:heading>

                    <div class="space-y-4">
                        @foreach($vehicles as $index => $v)
                            <div wire:key="v-{{ $v['id'] }}"
                                class="relative group bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all">
                                <div class="flex flex-col lg:flex-row">
                                    <div class="lg:w-[45%] w-full bg-zinc-100 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700 relative min-h-[300px] lg:min-h-full"
                                        x-data="{ activeSlide: 0, slides: @js($v['details']->copartCarPhotoUrls() ?? []) }">

                                        <template x-for="(slide, index) in slides" :key="index">
                                            <div x-show="activeSlide === index" x-transition.opacity.duration.300ms
                                                class="absolute inset-0">
                                                <img :src="slide" class="w-full h-full object-cover">
                                            </div>
                                        </template>

                                        {{-- Navigation Controls --}}
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

                                        {{-- Pagination Dots --}}
                                        <div x-show="slides.length > 1"
                                            class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1.5 z-10">
                                            <template x-for="(slide, index) in slides" :key="'dot-'+index">
                                                <button type="button" @click="activeSlide = index"
                                                    :class="activeSlide === index ? 'bg-white scale-110' : 'bg-white/50'"
                                                    class="w-2 h-2 rounded-full transition-all shadow-sm"></button>
                                            </template>
                                        </div>

                                        {{-- Fallback Empty State --}}
                                        <div x-show="slides.length === 0"
                                            class="absolute inset-0 flex flex-col items-center justify-center text-zinc-400">
                                            <flux:icon.photo class="size-12 mb-2 opacity-50" />
                                            <span class="font-medium text-sm">{{ __('No photos available') }}</span>
                                        </div>
                                    </div>

                                    {{-- Vehicle Details Grid --}}
                                    <div class="lg:w-[55%] w-full p-4 lg:p-5 flex flex-col">
                                        <div class="flex items-start justify-between mb-4">
                                            <div>
                                                <h4 class="font-bold text-xl text-zinc-900 dark:text-white leading-tight">
                                                    {{ $v['details']['year'] ?? '' }} {{ $v['details']['make'] ?? '' }}
                                                    {{ $v['details']['model'] ?? '' }}
                                                </h4>
                                                <p
                                                    class="text-[8px] text-zinc-500 mt-1 uppercase tracking-widest font-bold">
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
                                                <p
                                                    class="text-[10px] text-zinc-500 font-bold uppercase tracking-widest mb-1">
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
                                                <p
                                                    class="text-[10px] text-zinc-500 font-bold uppercase tracking-widest mb-1">
                                                    {{ __('Car Keys') }}
                                                </p>
                                                <p class="font-medium text-zinc-900 dark:text-zinc-100">
                                                    @if(($v['details']['car_keys'] ?? null) === '1')
                                                        <span
                                                            class="text-green-600 dark:text-green-400 flex items-center gap-1">
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
                                                <p
                                                    class="text-[10px] text-zinc-500 font-bold uppercase tracking-widest mb-1">
                                                    {{ __('Location') }}
                                                </p>
                                                <p
                                                    class="font-medium text-zinc-900 dark:text-zinc-100 text-sm whitespace-normal break-words">
                                                    {{ ($v['details']['location'] ?? null) ?: '—' }}
                                                </p>
                                            </div>
                                            <div>
                                                <p
                                                    class="text-[10px] text-zinc-500 font-bold uppercase tracking-widest mb-1">
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
                                            <flux:select wire:model="consignee_id"
                                                :placeholder="__('Select consignee')">
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
                                        @if($shipper_id)
                                            <flux:button type="button" wire:click="$set('showConsigneeModal', true)"
                                                icon="plus" class="shrink-0">
                                                {{ __('New') }}
                                            </flux:button>
                                        @endif
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
                                <flux:select wire:model="origin_port_id" label="{{ __('Origin Port') }}" icon="map-pin"
                                    :disabled="(bool) $targetShipment">
                                    <flux:select.option value="">{{ __('Select Port') }}</flux:select.option>
                                    @foreach ($this->shipmentOriginPorts as $port)
                                        <flux:select.option value="{{ $port->id }}">
                                            {{ $port->name }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>

                                <flux:select wire:model="destination_port_id" label="{{ __('Destination Port') }}"
                                    icon="flag" :disabled="(bool) $targetShipment">
                                    <flux:select.option value="">{{ __('Select Port') }}</flux:select.option>
                                    @foreach ($this->shipmentDestinationPorts as $port)
                                        <flux:select.option value="{{ $port->id }}">
                                            {{ $port->name }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>

                                <flux:select wire:model="logistics_service" label="{{ __('Service Type') }}"
                                    icon="briefcase" :disabled="(bool) $targetShipment">
                                    @foreach(LogisticsService::cases() as $service)
                                        <flux:select.option value="{{ $service->value }}">{{ $service->name }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>

                                <flux:field>
                                    <flux:label class="mb-2">{{ __('Shipping Mode') }}</flux:label>
                                    @if(count($vehicles) <= 1)
                                        <flux:radio.group wire:model.live="shipping_mode" variant="segmented"
                                            :disabled="(bool) $targetShipment" class="w-full!">
                                            <flux:radio :label="__('RoRo')" value="roro" icon="car-front" />
                                            <flux:radio :label="__('Container')" value="container" icon="container" />
                                        </flux:radio.group>
                                    @else
                                        <div
                                            class="flex items-center gap-2 p-3 bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700 rounded-xl">
                                            <flux:icon.container class="size-5 text-indigo-500" />
                                            <flux:text font="medium">{{ __('Container (Locked)') }}</flux:text>
                                            <flux:badge size="xs" color="indigo" variant="subtle" class="ml-auto">
                                                {{ count($vehicles) }} {{ __('Vehicles') }}
                                            </flux:badge>
                                        </div>
                                    @endif
                                    <flux:error name="shipping_mode" />
                                </flux:field>

                                <flux:select wire:model="carrier_id" label="{{ __('Default Carrier') }}"
                                    icon="building-office" :disabled="(bool) $targetShipment">
                                    <flux:select.option value="">{{ __('Select Carrier') }}</flux:select.option>
                                    @foreach(Carrier::all() as $car)
                                        <flux:select.option value="{{ $car->id }}">{{ $car->name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>
                        </x-crud.panel>
                    </div>

                    {{-- Right Column: Status & Preview --}}
                    <div class="space-y-6">
                        <x-crud.panel
                            class="p-6 bg-zinc-50 dark:bg-zinc-800/50 border-zinc-200 dark:border-zinc-700 shadow-sm!">
                            <flux:heading size="lg" class="mb-4">{{ __('Workflow Status') }}</flux:heading>
                            <div class="space-y-4">
                                <flux:select wire:model="shipment_status" label="{{ __('Initial status') }}"
                                    icon="clock" :disabled="(bool) $targetShipment">
                                    @foreach(ShipmentStatus::cases() as $status)
                                        <flux:select.option value="{{ $status->value }}">{{ $status->name }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:select wire:model="invoice_status" label="{{ __('Invoice Status') }}"
                                    icon="document-text" :disabled="(bool) $targetShipment">
                                    @foreach(InvoiceStatus::cases() as $status)
                                        <flux:select.option value="{{ $status->value }}">{{ $status->name }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:select wire:model="payment_status" label="{{ __('Payment Status') }}"
                                    icon="credit-card" :disabled="(bool) $targetShipment">
                                    @foreach(PaymentStatus::cases() as $status)
                                        <flux:select.option value="{{ $status->value }}">{{ $status->name }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:select wire:model="initial_vehicle_status"
                                    label="{{ __('Initial Vehicle Status') }}" icon="car-front">
                                    @foreach(VehicleStatus::cases() as $status)
                                        <flux:select.option value="{{ $status->value }}">{{ $status->name }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:select wire:model="payment_method_id" label="{{ __('Payment method') }}"
                                    icon="banknotes" :disabled="(bool) $targetShipment">
                                    <flux:select.option value="">{{ __('None') }}</flux:select.option>
                                    @foreach(PaymentMethod::query()->orderBy('name')->get() as $method)
                                        <flux:select.option value="{{ $method->id }}">{{ $method->name }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>
                        </x-crud.panel>

                        <div class="flex flex-col gap-3">
                            <flux:button type="submit" variant="primary" icon="check-circle" class="w-full h-12!">
                                {{ __('Save & Initialize Shipment') }}
                            </flux:button>
                            <flux:button :href="route('prealerts.index')" variant="ghost" class="w-full">
                                {{ __('Cancel') }}
                            </flux:button>
                        </div>
                    </div>
                </div>
            </form>
            <flux:modal wire:model.self="showConsigneeModal" class="max-w-md">
                <form wire:submit="createConsignee" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Add Consignee') }}</flux:heading>
                        <flux:subheading>{{ __('Create a new consignee for the selected shipper.') }}</flux:subheading>
                    </div>

                    <div class="space-y-4">
                        <flux:input wire:model="newConsigneeName" :label="__('Full Name')" required />
                        <flux:textarea wire:model="newConsigneeAddress" :label="__('Address (Optional)')" rows="3" />
                    </div>

                    <div class="flex justify-end gap-2">
                        <flux:modal.close>
                            <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button variant="primary" type="submit">{{ __('Add Consignee') }}</flux:button>
                    </div>
                </form>
            </flux:modal>
        </div>
</x-crud.page-shell>