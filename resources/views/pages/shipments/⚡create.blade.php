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
use App\Notifications\ShipmentCreatedNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Spatie\Permission\Models\Role;
use WireUi\Traits\WireUiActions;

new #[Title('Create Shipment')] class extends Component {
    use WireUiActions;

    #[Url]
    public ?int $prealert = null;

    // Form Properties
    public string $reference_no = '';
    public ?int $shipper_id = null;
    public ?int $consignee_id = null;
    public ?string $vin = '';
    public ?string $gatepass_pin = '';
    public ?int $vehicle_id = null;
    public ?int $carrier_id = null;
    public ?int $origin_port_id = null;
    public ?int $destination_port_id = null;
    public ?string $auction_receipt = '';
    public string $logistics_service = '';
    public string $shipping_mode = '';
    public string $shipment_status = '';
    public string $invoice_status = '';
    public string $payment_status = '';
    public ?int $payment_method_id = null;
    public ?string $notes = '';
    public bool $showConsigneeModal = false;
    public string $newConsigneeName = '';
    public string $newConsigneeAddress = '';

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
        $this->payment_status = $defaults->payment_status->value ?? PaymentStatus::Pending->value;
        $this->payment_method_id = $defaults->payment_method_id;

        // 2. Override from Prealert if provided
        if ($this->prealert) {
            $pre = Prealert::findOrFail($this->prealert);
            $this->shipper_id = $pre->shipper_id;
            $this->consignee_id = $pre->consignee_id;
            $this->vin = $pre->vin;
            $this->gatepass_pin = $pre->gatepass_pin;
            $this->vehicle_id = $pre->vehicle_id;
            $this->carrier_id = $pre->carrier_id;
            $this->destination_port_id = $pre->destination_port_id;
            $this->auction_receipt = $pre->auction_receipt;
            $this->notes = $pre->notes;

            // Load context
            $this->selectedVehicle = Vehicle::find($this->vehicle_id);
            $this->selectedShipper = Shipper::with('user')->find($this->shipper_id);
        }

        // 3. Generate Reference Number
        $this->generateReferenceNo($system);
    }

    protected function generateReferenceNo(SystemSetting $system): void
    {
        $prefix = $system->tracking_delivery_prefix ?: 'SHP';
        
        if ($system->tracking_number_type === 'random') {
            $this->reference_no = $prefix . '-' . strtoupper(Str::random($system->tracking_random_digits ?: 8));
        } else {
            // Auto Increment Logic
            $lastId = Shipment::max('id') ?? 0;
            $nextNumber = $lastId + 1;
            $digits = $system->tracking_digits ?: 5;
            $this->reference_no = $prefix . '-' . str_pad((string) $nextNumber, $digits, '0', STR_PAD_LEFT);
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'reference_no' => 'required|string|unique:shipments,reference_no',
            'shipper_id' => 'required|exists:shippers,id',
            'consignee_id' => 'required|exists:consignees,id',
            'vin' => 'required|string|unique:shipments,vin',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'origin_port_id' => 'nullable|exists:ports,id',
            'destination_port_id' => 'nullable|exists:ports,id',
            'logistics_service' => 'required|string',
            'shipping_mode' => 'required|string',
            'shipment_status' => 'required|string',
            'invoice_status' => 'required|string',
            'payment_status' => 'required|string',
            'payment_method_id' => 'nullable|integer|exists:payment_methods,id',
        ]);

        try {
            DB::transaction(function () {
                // 1. Create Shipment
                $shipment = Shipment::create([
                    'reference_no' => $this->reference_no,
                    'shipper_id' => $this->shipper_id,
                    'consignee_id' => $this->consignee_id,
                    'vin' => $this->vin,
                    'gatepass_pin' => $this->gatepass_pin,
                    'vehicle_id' => $this->vehicle_id,
                    'carrier_id' => $this->carrier_id,
                    'origin_port_id' => $this->origin_port_id,
                    'destination_port_id' => $this->destination_port_id,
                    'auction_receipt' => $this->auction_receipt,
                    'logistics_service' => $this->logistics_service,
                    'shipping_mode' => $this->shipping_mode,
                    'shipment_status' => $this->shipment_status,
                    'invoice_status' => $this->invoice_status,
                    'payment_status' => $this->payment_status,
                    'payment_method_id' => $this->payment_method_id,
                ]);

                // 2. Create Initial Tracking
                ShipmentTracking::create([
                    'shipment_id' => $shipment->id,
                    'status' => $this->shipment_status,
                    'note' => __('Initial record created.'),
                    'metadata' => [
                        'source' => 'shipment_create',
                        'created_by' => Auth::id(),
                    ],
                    'recorded_at' => now(),
                ]);

                // 3. Create Invoice
                Invoice::create([
                    'shipment_id' => $shipment->id,
                    'invoice_number' => 'INV-' . strtoupper(Str::random(8)),
                    'status' => $this->invoice_status,
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'total_amount' => 0,
                    'issued_at' => now(),
                ]);

                // 4. Create Activity Log
                ActivityLog::create([
                    'shipment_id' => $shipment->id,
                    'user_id' => Auth::id(),
                    'action' => 'created',
                    'properties' => [
                        'message' => __('Shipment created from prealert ID: ') . ($this->prealert ?: 'N/A'),
                        'source' => 'shipment_create',
                        'prealert_id' => $this->prealert,
                    ],
                ]);

                // 5. Delete Prealert after conversion
                if ($this->prealert) {
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
                    ->merge(User::query()->whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->pluck('id'))
                    ->when($shipment->shipper?->user_id, fn ($q) => $q->push($shipment->shipper->user_id))
                    ->unique()
                    ->values();

                $recipients = User::query()->whereIn('id', $recipientIds)->get();

                if ($recipients->isNotEmpty()) {
                    Notification::send($recipients, new ShipmentCreatedNotification($shipment));
                }
                
                $this->dialog()->show([
                    'icon' => 'success',
                    'title' => __('Success!'),
                    'description' => __('Shipment created successfully  .'),
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

        if (! $this->shipper_id) {
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
        if (! $this->shipper_id) {
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
        {{-- Top Section: Reference & Shipper (Read Only) --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Reference Card --}}
            <x-crud.panel class="p-6 border-indigo-100 dark:border-indigo-900/40 bg-linear-to-br from-indigo-50/50 to-white dark:from-indigo-900/10 dark:to-zinc-900 flex flex-col justify-center">
                <flux:text size="xs" class="uppercase tracking-widest font-bold text-indigo-500 mb-1">{{ __('Active Shipment Reference') }}</flux:text>
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-indigo-100 dark:bg-indigo-900/50 p-3 text-indigo-600 dark:text-indigo-400">
                        <flux:icon.qr-code class="size-8" />
                    </div>
                    <div>
                        <flux:heading size="xl" class="font-mono! tracking-tighter">{{ $reference_no }}</flux:heading>
                        <flux:text size="sm" class="text-zinc-500">{{ __('Initialized from Prealert') }}</flux:text>
                    </div>
                </div>
            </x-crud.panel>

            {{-- Shipper Profile Card --}}
            @if($selectedShipper)
                <x-crud.panel class="lg:col-span-2 p-6 flex flex-col justify-center">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <flux:avatar :name="$selectedShipper->user->name" size="lg" class="bg-indigo-100! text-indigo-700!" />
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
                             <flux:badge color="indigo" variant="subtle" size="sm" icon="shield-check">{{ __('Verified Shipper') }}</flux:badge>
                        </div>
                    </div>
                </x-crud.panel>
            @endif
        </div>

        {{-- Vehicle Section: Read Only --}}
        @if($selectedVehicle)
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Photos Carousel --}}
                <div class="lg:col-span-2">
                    <x-crud.panel class="overflow-hidden p-0 h-full min-h-[450px]">
                        @php $photos = $selectedVehicle->copartCarPhotoUrls(); @endphp
                        @if(count($photos) > 0)
                            <div x-data="{ 
                                active: 0, 
                                photos: {{ json_encode($photos) }},
                                next() { this.active = (this.active + 1) % this.photos.length },
                                prev() { this.active = (this.active - 1 + this.photos.length) % this.photos.length }
                            }" class="relative h-full w-full group">
                                <img :src="photos[active]" class="h-full w-full object-cover transition-all duration-500 ease-in-out" />
                                
                                <div class="absolute inset-x-0 bottom-0 bg-linear-to-t from-black/80 to-transparent p-8">
                                    <flux:heading class="text-white! text-2xl!">{{ $selectedVehicle->year }} {{ $selectedVehicle->make }} {{ $selectedVehicle->model }}</flux:heading>
                                    <div class="flex items-center gap-4 mt-2">
                                        <flux:badge color="white" variant="solid" size="sm" icon="finger-print" class="text-zinc-900!">{{ $selectedVehicle->vin }}</flux:badge>
                                        <flux:badge color="indigo" variant="solid" size="sm" icon="ticket">{{ $selectedVehicle->lot_number }}</flux:badge>
                                    </div>
                                </div>

                                @if(count($photos) > 1)
                                    <button type="button" @click="prev()" class="absolute left-4 top-1/2 -translate-y-1/2 p-3 bg-black/30 hover:bg-black/50 rounded-full text-white opacity-0 group-hover:opacity-100 transition-opacity backdrop-blur-subtle">
                                        <flux:icon.chevron-left class="size-6" />
                                    </button>
                                    <button type="button" @click="next()" class="absolute right-4 top-1/2 -translate-y-1/2 p-3 bg-black/30 hover:bg-black/50 rounded-full text-white opacity-0 group-hover:opacity-100 transition-opacity backdrop-blur-subtle">
                                        <flux:icon.chevron-right class="size-6" />
                                    </button>
                                    
                                    <div class="absolute bottom-6 left-1/2 -translate-x-1/2 flex gap-1.5">
                                        <template x-for="(photo, index) in photos">
                                            <div @click="active = index" :class="active === index ? 'bg-white w-6' : 'bg-white/30 hover:bg-white/50 w-2'" class="h-1.5 rounded-full transition-all duration-300 cursor-pointer"></div>
                                        </template>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="h-full flex flex-col items-center justify-center p-12 text-zinc-400 bg-zinc-50 dark:bg-zinc-900">
                                <flux:icon.camera class="size-16 mb-4 opacity-20" />
                                <flux:text>{{ __('No photos available for this vehicle.') }}</flux:text>
                            </div>
                        @endif
                    </x-crud.panel>
                </div>

                {{-- Vehicle Specs Grid --}}
                <div class="space-y-6">
                    <x-crud.panel class="p-6 h-full">
                        <flux:heading size="lg" class="mb-6 border-b pb-3 border-zinc-100 dark:border-zinc-800 flex items-center gap-2">
                             <flux:icon.document-magnifying-glass class="size-5 text-indigo-500" />
                             {{ __('Contextual Details') }}
                        </flux:heading>
                        
                        <div class="grid grid-cols-2 gap-y-6 gap-x-6">
                            <div>
                                <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">{{ __('VIN') }}</flux:text>
                                <flux:text class="font-mono font-semibold">{{ $selectedVehicle->vin }}</flux:text>
                            </div>
                            <div>
                                <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">{{ __('Lot #') }}</flux:text>
                                <flux:text class="font-mono font-semibold text-indigo-600 dark:text-indigo-400">{{ $selectedVehicle->lot_number ?: 'N/A' }}</flux:text>
                            </div>
                            <div>
                                <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">{{ __('Body') }}</flux:text>
                                <flux:text class="font-medium">{{ $selectedVehicle->body_style ?: '—' }}</flux:text>
                            </div>
                            <div>
                                <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">{{ __('Type') }}</flux:text>
                                <flux:text class="font-medium">{{ $selectedVehicle->vehicle_type ?: '—' }}</flux:text>
                            </div>
                            <div>
                                <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">{{ __('Damage') }}</flux:text>
                                <flux:badge color="rose" variant="subtle" size="sm">{{ $selectedVehicle->primary_damage ?: 'None' }}</flux:badge>
                            </div>
                            <div>
                                <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-1">{{ __('Gatepass') }}</flux:text>
                                <flux:text class="font-mono text-emerald-600 dark:text-emerald-400 font-bold">{{ $gatepass_pin ?: '—' }}</flux:text>
                            </div>
                        </div>

                        <div class="mt-8 pt-6 border-t border-zinc-100 dark:border-zinc-800">
                             <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 mb-3">{{ __('Attachments') }}</flux:text>
                             <div class="flex items-center gap-3 p-3 rounded-xl border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors group cursor-pointer">
                                 <div class="p-2 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg text-indigo-600 dark:text-indigo-400 group-hover:scale-110 transition-transform">
                                     <flux:icon.document-arrow-down class="size-6" />
                                 </div>
                                 <div>
                                     <flux:text size="sm" class="font-bold!">{{ __('Auction Receipt') }}</flux:text>
                                     <flux:text size="xs" class="text-zinc-500 font-mono">{{ Str::limit($auction_receipt ?: 'receipt.pdf', 20) }}</flux:text>
                                 </div>
                             </div>
                        </div>
                    </x-crud.panel>
                </div>
            </div>
        @endif

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
                                                        <span class="ml-2 text-[10px] font-bold uppercase tracking-widest text-zinc-400">
                                                            ({{ __('Default') }})
                                                        </span>
                                                    @endif
                                                </flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    </div>
                                    @if($shipper_id)
                                        <flux:button type="button" wire:click="$set('showConsigneeModal', true)" icon="plus" class="shrink-0">
                                            {{ __('New') }}
                                        </flux:button>
                                    @endif
                                </div>
                                <flux:error name="consignee_id" />
                            </flux:field>
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

                            <flux:select wire:model="destination_port_id" label="{{ __('Destination Port') }}" icon="flag">
                                <flux:select.option value="">{{ __('Select Port') }}</flux:select.option>
                                @foreach ($this->shipmentDestinationPorts as $port)
                                    <flux:select.option value="{{ $port->id }}">
                                        {{ $port->name }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model="logistics_service" label="{{ __('Service Type') }}" icon="briefcase">
                                @foreach(LogisticsService::cases() as $service)
                                    <flux:select.option value="{{ $service->value }}">{{ $service->name }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model="shipping_mode" label="{{ __('Shipping Mode') }}" icon="cube">
                                @foreach(ShippingMode::cases() as $mode)
                                    <flux:select.option value="{{ $mode->value }}">{{ $mode->name }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model="carrier_id" label="{{ __('Default Carrier') }}" icon="building-office">
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
                    <x-crud.panel class="p-6 bg-zinc-50 dark:bg-zinc-800/50 border-zinc-200 dark:border-zinc-700 shadow-sm!">
                        <flux:heading size="lg" class="mb-4">{{ __('Workflow Status') }}</flux:heading>
                        
                        <div class="space-y-4">
                            <flux:select wire:model="shipment_status" label="{{ __('Initial status') }}" icon="clock">
                                @foreach(ShipmentStatus::cases() as $status)
                                    <flux:select.option value="{{ $status->value }}">{{ $status->name }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model="invoice_status" label="{{ __('Invoice Status') }}" icon="document-text">
                                @foreach(InvoiceStatus::cases() as $status)
                                    <flux:select.option value="{{ $status->value }}">{{ $status->name }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model="payment_status" label="{{ __('Payment Status') }}" icon="credit-card">
                                @foreach(PaymentStatus::cases() as $status)
                                    <flux:select.option value="{{ $status->value }}">{{ $status->name }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model="payment_method_id" label="{{ __('Payment method') }}" icon="banknotes">
                                <flux:select.option value="">{{ __('None') }}</flux:select.option>
                                @foreach(PaymentMethod::query()->orderBy('name')->get() as $method)
                                    <flux:select.option value="{{ $method->id }}">{{ $method->name }}</flux:select.option>
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
