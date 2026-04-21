<?php

declare(strict_types=1);

use App\Models\Carrier;
use App\Models\Consignee;
use App\Models\Port;
use App\Models\Prealert;
use App\Models\Shipper;
use App\Models\User;
use App\Enums\ShippingMode;
use App\Models\Shipment;
use App\Notifications\PrealertCreatedNotification;
use Spatie\Permission\Models\Role;
use App\Models\Vehicle;
use App\Services\VinLookupService;
use App\Enums\VinLookupOutcome;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use WireUi\Traits\WireUiActions;

new #[Title('Submit Prealert')] class extends Component {
    use WithFileUploads;
    use WireUiActions;

    public ?int $shipper_id = null;
    public ?int $consignee_id = null;
    public ?int $notify_party_id = null;
    public string $vin = '';
    public ?int $carrier_id = null;
    public ?int $destination_port_id = null;

    // Logistics fields
    public string $shipping_mode = 'roro';
    public ?int $shipment_id = null;

    public array $vehicles = []; // items: ['id' => int, 'vin' => string, 'details' => Vehicle, 'receipt_path' => string, 'gatepass_pin' => string]
    /** @var array<int, mixed> */
    public array $receipt_files = []; // Indexed by index to handle uploads

    public bool $showConsigneeModal = false;
    public string $newConsigneeName = '';
    public string $newConsigneeAddress = '';
    public ?int $newConsigneeCountryId = null;
    public ?int $newConsigneeStateId = null;

    public bool $loadingVehicle = false;
    public ?string $vinError = null;

    public function mount(): void
    {
        $user = Auth::user();
        if (!$user?->hasRole('super_admin') && !$user?->staff()->exists()) {
            $this->shipper_id = $user?->shipper?->id;

            if ($this->shipper_id) {
                $this->assignDefaultConsignee();
            }
        }
    }

    public function updatedShipperId(): void
    {
        $this->consignee_id = null;
        $this->notify_party_id = null;
        if ($this->shipper_id) {
            $this->assignDefaultConsignee();
        }
    }

    private function assignDefaultConsignee(): void
    {
        $default = Consignee::where('shipper_id', $this->shipper_id)
            ->where('is_default', true)
            ->first();

        if ($default) {
            $this->consignee_id = $default->id;
        }
    }

    public function updatedVin(string $value): void
    {
        $this->vin = strtoupper(trim($value));
        $this->vinError = null;

        if (strlen($this->vin) === 17) {
            if (count($this->vehicles) >= 5) {
                $this->vinError = __('Maximum of 5 vehicles reached. No more vehicles can be added.');
                $this->notification()->warning(__('Maximum of 5 vehicles reached. No more vehicles can be added.'));
                $this->vin = '';
                return;
            }

            foreach ($this->vehicles as $v) {
                if ($v['vin'] === $this->vin) {
                    $this->vinError = __('This vehicle is already in your list.');
                    $this->vin = '';
                    return;
                }
            }
            $this->lookupVin();
        }
    }

    public function lookupVin(): void
    {
        $user = Auth::user();
        $isAdminOrStaff = $user?->hasRole('super_admin') || $user?->staff()->exists();

        if (!$this->shipper_id && !$isAdminOrStaff) {
            $this->vinError = __('Please select a shipper first.');
            return;
        }

        $this->loadingVehicle = true;
        try {
            $service = app(VinLookupService::class);
            // Use 0 for admin rate limiting if no shipper selected yet
            $lookupShipperId = $this->shipper_id ?? 0;
            $result = $service->lookup($this->vin, $lookupShipperId);

            if ($result->outcome === VinLookupOutcome::VehicleReady || $result->outcome === VinLookupOutcome::FetchedFromApi) {
                $vehicle = $result->vehicle;

                // Add to list, keyed by ID for strict DOM binding sync
                $this->vehicles[$vehicle->id] = [
                    'id' => $vehicle->id,
                    'vin' => $vehicle->vin,
                    'details' => [
                        'year' => $vehicle->year,
                        'make' => $vehicle->make,
                        'model' => $vehicle->model,
                        'color' => $vehicle->color,
                        'car_keys' => $vehicle->car_keys,
                        'lot_number' => $vehicle->lot_number,
                        'est_retail_value' => $vehicle->est_retail_value,
                        'location' => $vehicle->location,
                        'auction_name' => $vehicle->auction_name,
                        'photos' => $vehicle->copartCarPhotoUrls(),
                    ],
                    'gatepass_pin' => '',
                ];

                $this->vin = '';
                $this->notification()->success(__('Vehicle added to list.'));
            } else {
                $this->vinError = $result->message;

                if ($result->outcome === VinLookupOutcome::AlreadyOnShipment) {
                    $this->notification()->warning($result->message);
                } elseif ($result->outcome === VinLookupOutcome::RateLimited) {
                    $this->notification()->warning($result->message);
                } else {
                    $this->notification()->error($result->message);
                }
            }
        } catch (\Exception $e) {
            $this->vinError = __('An error occurred during VIN lookup.');
        } finally {
            $this->loadingVehicle = false;
            if (count($this->vehicles) > 1) {
                $this->shipping_mode = 'container';
            }
        }
    }

    public function removeVehicle(int $id): void
    {
        unset($this->vehicles[$id]);

        // Dynamic mode revert
        if (count($this->vehicles) > 1) {
            $this->shipping_mode = 'container';
        }
        if ($this->shipping_mode === 'roro') {
            $this->shipment_id = null;
        }

        $this->notification()->info(__('Vehicle removed from list.'));
    }

    public function updatedShipmentId(): void
    {
        if (!$this->shipment_id) {
            return;
        }

        $target = Shipment::withCount('vehicles')->find($this->shipment_id);
        if (!$target) {
            return;
        }

        $total = count($this->vehicles) + $target->vehicles_count;
        if ($total > 5) {
            $this->notification()->warning(__('Warning: Adding these :count vehicles to this container will exceed the 5-vehicle capacity limit. Please select a different container or reduce your vehicle list.', ['count' => count($this->vehicles)]));
        }
    }

    public function save(): void
    {
        $this->validate([
            'shipper_id' => ['required', 'exists:shippers,id'],
            'consignee_id' => ['nullable', 'exists:consignees,id'],
            'notify_party_id' => ['nullable', 'exists:consignees,id'],
            'vehicles' => ['required', 'array', 'min:1'],
            'vehicles.*.gatepass_pin' => ['nullable', 'string', 'max:11'],
            'shipping_mode' => ['required', 'string'],
            'shipment_id' => ['nullable', 'exists:shipments,id'],
            'carrier_id' => ['nullable', 'exists:carriers,id'],
            'destination_port_id' => ['nullable', 'exists:ports,id'],
        ]);

        // Capacity constraint when linking to an existing container
        if ($this->shipment_id) {
            $target = Shipment::withCount('vehicles')->find($this->shipment_id);
            if ($target && (count($this->vehicles) + $target->vehicles_count) > 5) {
                $this->notification()->error(__('Action impossible: Adding these vehicles to the selected container would exceed the 5-vehicle capacity limit.'));

                return;
            }
        }

        // Process file uploads for each vehicle
        foreach ($this->vehicles as $index => &$v) {
            if (isset($this->receipt_files[$index])) {
                $v['receipt_path'] = $this->receipt_files[$index]->store('prealerts/receipts', 'public');
            }
        }
        unset($v); // Break the reference to the last element

        foreach ($this->vehicles as $v) {
            $vehicle = Vehicle::find($v['id']);

            if ($vehicle && $vehicle->prealert_id) {
                $this->notification()->warning(
                    title: __('Duplicate Vehicle'),
                    description: __(':vin is already in another prealert.', ['vin' => $v['vin']])
                );
                return;
            }

            if ($vehicle && $vehicle->shipment_id) {
                $this->notification()->warning(
                    title: __('Duplicate Vehicle'),
                    description: __(':vin is already assigned to a shipment.', ['vin' => $v['vin']])
                );
                return;
            }
        }

        $prealert = Prealert::create([
            'shipper_id' => $this->shipper_id,
            'consignee_id' => $this->consignee_id,
            'notify_party_id' => $this->notify_party_id,
            'carrier_id' => (int) $this->carrier_id ?: null,
            'destination_port_id' => (int) $this->destination_port_id ?: null,
            'shipping_mode' => $this->shipping_mode,
            'shipment_id' => $this->shipment_id,
        ]);

        foreach ($this->vehicles as $vData) {
            $vehicle = Vehicle::find($vData['id']);
            if ($vehicle) {
                $vehicle->update([
                    'prealert_id' => $prealert->id,
                    'auction_receipt' => $vData['receipt_path'] ?? null,
                    'gatepass_pin' => $vData['gatepass_pin'] ?: null,
                ]);
            }
        }

        // Identify all non-shipper administrative staff roles
        $adminRoleNames = Role::query()
            ->where('name', '!=', 'shipper')
            ->pluck('name');

        // Identify all recipients (Non-shipper roles, staff, and the specific shipper owner)
        $recipientIds = User::query()
            ->role($adminRoleNames)
            ->pluck('id')
            ->merge(User::query()->whereHas('staff')->pluck('id'))
            ->when($prealert->shipper?->user_id, fn($q) => $q->push($prealert->shipper->user_id))
            ->unique()
            ->values();


        $recipients = User::query()->whereIn('id', $recipientIds)->get();

        if ($recipients->isNotEmpty()) {
            \Illuminate\Support\Facades\Notification::send($recipients, new PrealertCreatedNotification($prealert));
        }


        $this->dialog()->show([
            'icon' => 'success',
            'title' => __('Success!'),
            'description' => $this->shipping_mode === 'container'
                ? __('Container prealert submitted successfully.')
                : __('RoRo prealert submitted successfully.'),
            'onClose' => [
                'method' => 'redirectToPrealerts',
            ],
        ]);
    }

    public function redirectToPrealerts(): void
    {
        $this->redirectRoute('prealerts.index', navigate: true);
    }

    public function createConsignee(): void
    {
        $this->validate([
            'newConsigneeName' => ['required', 'string', 'max:255'],
            'newConsigneeAddress' => ['nullable', 'string', 'max:500'],
            'newConsigneeCountryId' => ['nullable', 'integer', 'exists:countries,id'],
            'newConsigneeStateId' => ['nullable', 'integer', 'exists:states,id'],
        ]);

        if (!$this->shipper_id) {
            $this->notification()->error(__('Please select a shipper first.'));
            return;
        }

        $consignee = Consignee::create([
            'shipper_id' => $this->shipper_id,
            'name' => $this->newConsigneeName,
            'address' => $this->newConsigneeAddress,
            'country_id' => $this->newConsigneeCountryId,
            'state_id' => $this->newConsigneeStateId,
            'is_default' => false,
        ]);

        $this->consignee_id = $consignee->id;
        $this->showConsigneeModal = false;
        $this->reset(['newConsigneeName', 'newConsigneeAddress', 'newConsigneeCountryId', 'newConsigneeStateId']);
        unset($this->consignees);

        $this->notification()->success(__('Consignee created successfully.'));
    }

    #[Computed]
    public function carriers()
    {
        return Carrier::orderBy('name')->get();
    }

    #[Computed]
    public function ports()
    {
        return Port::where('type', 'destination')
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
    public function openContainers()
    {
        if (!$this->shipper_id || $this->shipping_mode !== 'container') {
            return collect();
        }

        return Shipment::where('shipper_id', $this->shipper_id)
            ->where('shipping_mode', ShippingMode::Container)
            ->whereNull('sealed_at')
            ->withCount('vehicles')
            ->get()
            ->filter(fn($s) => $s->vehicles_count < $s->capacity);
    }
}; ?>

<div>
    <x-crud.page-shell max-width="max-w-screen-2xl">
        <div class="w-full mx-auto">
            <form wire:submit="save" class="space-y-6">
                <x-crud.panel
                    class="{{ $shipping_mode === 'roro'
    ? 'bg-emerald-50/40 dark:bg-emerald-950/10 border-emerald-200/50 dark:border-emerald-800/50'
    : 'bg-rose-50/40 dark:bg-rose-950/10 border-rose-200/50 dark:border-rose-800/50' }} p-4 sm:p-8 transition-colors duration-500">
                    <div class="space-y-8">
                        {{-- Mode Header --}}
                        <div class="flex flex-col gap-4 border-b border-zinc-200/50 dark:border-zinc-700/50 pb-6">
                            <div class="flex items-center gap-4">
                                <div
                                    class="shrink-0 p-3 rounded-2xl {{ $shipping_mode === 'roro' ? 'bg-emerald-100 dark:bg-emerald-900 text-emerald-600 dark:text-emerald-400' : 'bg-rose-100 dark:bg-rose-900 text-rose-600 dark:text-rose-400' }}">
                                    <flux:icon :name="$shipping_mode === 'roro' ? 'car-front' : 'container'"
                                        class="size-8" />
                                </div>
                                <flux:heading size="xl" class="font-bold tracking-tight">
                                    {{ $shipping_mode === 'roro' ? __('RoRo Prealert') : __('Container Prealert') }}
                                </flux:heading>
                            </div>

                            <div class="w-full">
                                @if(count($vehicles) <= 1)
                                    <flux:radio.group wire:model.live="shipping_mode" variant="segmented" size="sm"
                                        class="w-full sm:w-auto">
                                        <flux:radio :label="__('RoRo')" value="roro" icon="car-front" />
                                        <flux:radio :label="__('Container')" value="container" icon="container" />
                                    </flux:radio.group>
                                @else
                                    <flux:badge color="rose" variant="subtle" size="sm" icon="container">
                                        {{ __('Container Mode (Auto)') }}
                                    </flux:badge>
                                @endif
                            </div>
                        </div>

                        {{-- 1. VIN & Lookup --}}
                        <div
                            class="bg-white/50 dark:bg-zinc-900/30 rounded-2xl p-4 sm:p-6 border border-dashed {{ $shipping_mode === 'roro' ? 'border-emerald-200 dark:border-emerald-800' : 'border-rose-200 dark:border-rose-800' }} shadow-sm">
                            <flux:field>
                                <flux:label size="lg" class="mb-3 font-bold text-zinc-800 dark:text-zinc-200">
                                    {{ __('Add Vehicle VIN') }}
                                </flux:label>
                                <flux:input wire:model.live.debounce.500ms="vin" icon="identification"
                                    placeholder="{{ __('Enter VIN...') }}" maxlength="17"
                                    class="font-mono uppercase text-xl"></flux:input>
                                @if($vinError)
                                    <flux:error class="mt-1">{{ $vinError }}</flux:error>
                                @endif
                                <flux:description class="mt-1">
                                    {{ __('Search for a vehicle to add.') }}
                                </flux:description>
                            </flux:field>
                        </div>

                        {{-- Legacy Mode Selection Removed --}}

                        {{-- 2. Added Vehicles List --}}
                        @if(count($vehicles) > 0)
                            <div class="space-y-4">
                                <div class="flex items-center justify-between px-1">
                                    <flux:heading size="md">{{ __('Added Vehicles') }}</flux:heading>
                                    <flux:badge size="sm" color="zinc" variant="subtle">{{ count($vehicles) }}</flux:badge>
                                </div>

                                <div class="grid grid-cols-1 gap-4">
                                    @foreach($vehicles as $index => $v)
                                        <div wire:key="v-{{ $v['id'] }}"
                                            class="relative group bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all">
                                            <div class="flex flex-col lg:flex-row">
                                                <div class="lg:w-[45%] w-full bg-zinc-100 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700 relative min-h-[300px] lg:min-h-full"
                                                    x-data="{ activeSlide: 0, slides: @js($v['details']['photos'] ?? []) }">

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
                                                    <div class="flex items-start justify-between gap-2 mb-4">
                                                        <div class="min-w-0 flex-1">
                                                            <h4
                                                                class="font-bold text-xl text-zinc-900 dark:text-white leading-tight truncate">
                                                                {{ $v['details']['year'] ?? '' }}
                                                                {{ $v['details']['make'] ?? '' }}
                                                                {{ $v['details']['model'] ?? '' }}
                                                            </h4>
                                                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-2 mb-1">
                                                                <div class="min-w-0">
                                                                    <p
                                                                        class="text-[8px] text-zinc-500 uppercase tracking-widest font-bold">
                                                                        {{ __('VIN') }}
                                                                    </p>
                                                                    <p
                                                                        class="font-mono text-zinc-700 dark:text-zinc-300 font-medium text-sm mt-0.5 truncate">
                                                                        {{ $v['vin'] }}
                                                                    </p>
                                                                </div>
                                                                <div class="pl-4 border-l border-zinc-200 dark:border-zinc-700 shrink-0">
                                                                    <p
                                                                        class="text-[8px] text-zinc-500 uppercase tracking-widest font-bold">
                                                                        {{ __('Lot Number') }}
                                                                    </p>
                                                                    <p
                                                                        class="font-mono text-zinc-700 dark:text-zinc-300 font-medium text-sm mt-0.5">
                                                                        {{ ($v['details']['lot_number'] ?? null) ?: '—' }}
                                                                    </p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <button type="button" wire:click="removeVehicle({{ $v['id'] }})"
                                                            class="p-2 text-zinc-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/30 rounded-xl transition-colors shrink-0"
                                                            title="{{ __('Remove vehicle') }}">
                                                            <flux:icon.x-mark class="size-5" />
                                                        </button>
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
                                                                    <span
                                                                        class="text-red-500 flex items-center gap-1"><flux:icon.x-mark
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

                                                    {{-- Logistics Grid --}}
                                                    <div
                                                        class="mt-auto flex flex-col xl:flex-row gap-4 border-t border-zinc-100 dark:border-zinc-700/50 pt-4">
                                                        <flux:field class="flex-1 min-w-0">
                                                            <flux:label size="xs"
                                                                class="font-bold uppercase tracking-widest text-zinc-400 mb-1">
                                                                {{ __('Auction Receipt') }}
                                                            </flux:label>
                                                            <div class="flex items-center gap-2 w-full">
                                                                <input type="file" wire:model="receipt_files.{{ $index }}"
                                                                    id="receipt-{{ $index }}" class="sr-only" required>
                                                                <label for="receipt-{{ $index }}"
                                                                    class="w-full flex items-center justify-center gap-2 px-3 py-2 min-h-[40px] bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-700 rounded-lg cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 transition text-sm font-semibold">
                                                                    <flux:icon.paper-clip
                                                                        class="size-4 shrink-0 text-zinc-400" />
                                                                    @if(isset($receipt_files[$index]))
                                                                        <span
                                                                            class="text-indigo-600 dark:text-indigo-400 whitespace-normal break-words text-center">{{ $receipt_files[$index]->getClientOriginalName() }}</span>
                                                                    @else
                                                                        <span
                                                                            class="text-zinc-500 shrink-0">{{ __('Choose File') }}</span>
                                                                    @endif
                                                                </label>
                                                            </div>
                                                            <flux:error :name="'receipt_files.'.$index" />
                                                        </flux:field>

                                                        <flux:field class="xl:w-48 shrink-0">
                                                            <flux:label size="xs"
                                                                class="font-bold uppercase tracking-widest text-zinc-400 mb-1">
                                                                {{ __('Gatepass PIN') }}
                                                            </flux:label>
                                                            <flux:input wire:model="vehicles.{{ $index }}.gatepass_pin"
                                                                placeholder="{{ __('PIN code') }}" maxlength="11"
                                                                class="font-mono text-center tracking-widest font-bold w-full"
                                                                required />
                                                            <flux:error :name="'vehicles.'.$index.'.gatepass_pin'" />
                                                        </flux:field>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div
                                class="flex flex-col items-center justify-center py-12 px-4 border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-2xl bg-zinc-50/50 dark:bg-zinc-900/30">
                                <flux:icon.car-front class="size-12 text-zinc-300 mb-3" />
                                <p class="text-zinc-500 text-sm">
                                    {{ __('No vehicles added yet. Enter a VIN above to begin.') }}
                                </p>
                            </div>
                        @endif

                        {{-- 3. Common Logistics --}}
                        <div
                            class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                            <div class="md:col-span-2 flex flex-col gap-6">
                                <div>
                                    @if (Auth::user()?->hasRole('super_admin') || Auth::user()?->staff()->exists())
                                        <x-select wire:model.live="shipper_id" :label="__('Shipper')"
                                            :placeholder="__('Search and select shipper')" option-value="id"
                                            option-label="name" :async-data="route('api.shippers.search')" searchable
                                            required />
                                    @else
                                        <flux:input :label="__('Shipper')" :value="sprintf(
                                                                '%s(%s)',
                                                                Auth::user()?->name ?? '',
                                                                Auth::user()?->shipper?->company_name ?? '-'
                                                            )" disabled />
                                        <input type="hidden" wire:model="shipper_id">
                                    @endif
                                </div>

                                <flux:field>
                                    <flux:label class="mb-2">{{ __('Consignee') }}</flux:label>
                                    <div class="flex items-start gap-2">
                                        <div class="flex-1">
                                            <flux:select wire:model="consignee_id" :placeholder="__('Select consignee')"
                                                required>
                                                @foreach($this->consignees as $consignee)
                                                    <flux:select.option :value="$consignee->id">
                                                        {{ $consignee->name }}
                                                        @if($consignee->is_default)
                                                            <span
                                                                class="ml-2 text-[10px] font-bold uppercase tracking-widest text-zinc-400">({{ __('Default') }})</span>
                                                        @endif
                                                    </flux:select.option>
                                                @endforeach
                                            </flux:select>
                                        </div>
                                        @if($shipper_id)
                                            <flux:button variant="ghost" type="button"
                                                wire:click="$set('showConsigneeModal', true)" icon="plus" class="shrink-0">
                                                {{ __('New') }}
                                            </flux:button>
                                        @endif
                                    </div>
                                    <flux:error name="consignee_id" />
                                </flux:field>

                                <flux:select wire:model="notify_party_id" :label="__('Notify Party (Optional)')"
                                    :placeholder="__('Same as Consignee')">
                                    <flux:select.option value="">{{ __('Same as Consignee') }}</flux:select.option>
                                    @foreach($this->consignees as $consignee)
                                        <flux:select.option :value="$consignee->id">
                                            {{ $consignee->name }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>

                            @if($shipping_mode === 'container' && $shipper_id)
                                <flux:field>
                                    <flux:label class="text-xs font-bold uppercase tracking-wider text-zinc-400">
                                        {{ __('Link to Container') }}
                                    </flux:label>
                                    <flux:select wire:model.live="shipment_id"
                                        :placeholder="__('Select open container...')">
                                        <flux:select.option value="">{{ __('Start New Container') }}</flux:select.option>
                                        @foreach($this->openContainers as $s)
                                            <flux:select.option :value="$s->id">
                                                {{ $s->reference_no }} ({{ $s->vehicles_count }}/{{ $s->capacity }})
                                            </flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    @if($shipment_id)
                                        @php
                                            $selectedContainer = $this->openContainers->firstWhere('id', $shipment_id);
                                            $totalIfMerged = $selectedContainer ? count($vehicles) + $selectedContainer->vehicles_count : 0;
                                        @endphp
                                        @if($totalIfMerged > 5)
                                            <flux:callout color="rose" icon="exclamation-triangle" class="mt-2">
                                                <flux:callout.heading>{{ __('Capacity Exceeded') }}</flux:callout.heading>
                                                <flux:callout.text>
                                                    {{ __('Adding :count vehicles to this container would result in :total/5 — exceeding the limit.', ['count' => count($vehicles), 'total' => $totalIfMerged]) }}
                                                </flux:callout.text>
                                            </flux:callout>
                                        @elseif($totalIfMerged > 0)
                                            <flux:badge color="emerald" variant="subtle" size="sm" icon="check-circle" class="mt-1">
                                                {{ __(':total/5 vehicles after merge', ['total' => $totalIfMerged]) }}
                                            </flux:badge>
                                        @endif
                                    @endif
                                </flux:field>
                            @endif

                            <flux:select wire:model.live="carrier_id" label="{{ __('Carrier') }}"
                                :placeholder="__('Select carrier')" icon="car-front">
                                <flux:select.option value="">{{ __('None') }}</flux:select.option>
                                @foreach($this->carriers as $car)
                                    <flux:select.option :value="$car->id">{{ $car->name }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select wire:model.live="destination_port_id" label="{{ __('Destination Port') }}"
                                :placeholder="__('Select destination port')" icon="map-pin">
                                <flux:select.option value="">{{ __('None') }}</flux:select.option>
                                @foreach($this->ports as $port)
                                    <flux:select.option :value="$port->id">{{ $port->name }}</flux:select.option>
                                @endforeach
                            </flux:select>

                        </div>

                        <div class="flex items-center justify-end gap-3 mt-8">
                            <flux:button variant="ghost" :href="route('prealerts.index')" wire:navigate>
                                {{ __('Cancel') }}
                            </flux:button>
                            <flux:button variant="primary" type="submit"
                                :disabled="$loadingVehicle || count($vehicles) === 0" wire:loading.attr="disabled">
                                {{ __('Submit Prealert') }}
                            </flux:button>
                        </div>
                    </div>
                </x-crud.panel>
            </form>
        </div>
    </x-crud.page-shell>

    <flux:modal wire:model.self="showConsigneeModal" class="max-w-md">
        <form wire:submit="createConsignee" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Add Consignee') }}</flux:heading>
                <flux:subheading>{{ __('Create a new consignee for the selected shipper.') }}</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="newConsigneeName" :label="__('Full Name')" required />
                <flux:textarea wire:model="newConsigneeAddress" :label="__('Address')" rows="2" />
                <flux:select wire:model.live="newConsigneeCountryId" :label="__('Country')">
                    <flux:select.option value="">{{ __('Select country') }}</flux:select.option>
                    @foreach(\App\Models\Country::orderBy('name')->get() as $country)
                        <flux:select.option :value="$country->id">{{ $country->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                @if($newConsigneeCountryId)
                    <flux:select wire:model="newConsigneeStateId" :label="__('State / Region')">
                        <flux:select.option value="">{{ __('Select state') }}</flux:select.option>
                        @foreach(\App\Models\State::where('country_id', $newConsigneeCountryId)->orderBy('name')->get() as $state)
                            <flux:select.option :value="$state->id">{{ $state->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
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