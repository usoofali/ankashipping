<?php

declare(strict_types=1);

use App\Models\Carrier;
use App\Models\Consignee;
use App\Models\Port;
use App\Models\Prealert;
use App\Models\Shipper;
use App\Models\User;
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
    public string $vin = '';
    public string $gatepass_pin = '';
    public ?int $carrier_id = null;
    public ?int $destination_port_id = null;
    public $auction_receipt;
    public string $notes = '';

    public bool $showConsigneeModal = false;
    public string $newConsigneeName = '';
    public string $newConsigneeAddress = '';

    public ?Vehicle $vehicle = null;
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
        $this->vehicle = null;
        $this->vinError = null;

        if (strlen($this->vin) === 17) {
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
                $this->vehicle = $result->vehicle;
                $this->notification()->success(__('Vehicle details loaded successfully.'));
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
        }
    }

    public function save(): void
    {
        $this->validate([
            'shipper_id' => ['required', 'exists:shippers,id'],
            'consignee_id' => ['nullable', 'exists:consignees,id'],
            'vin' => ['required', 'string', 'size:17'],
            'carrier_id' => ['nullable', 'exists:carriers,id'],
            'destination_port_id' => ['nullable', 'exists:ports,id'],
            'gatepass_pin' => ['nullable', 'string', 'max:11'],
            'auction_receipt' => ['nullable', 'file', 'max:5120'], // 5MB
            'notes' => ['nullable', 'string'],
        ]);

        $path = $this->auction_receipt ? $this->auction_receipt->store('prealerts/receipts', 'public') : null;

        if ($this->vehicle?->id) {
            $exists = Prealert::where('vehicle_id', $this->vehicle->id)->exists();
            if ($exists) {
                $this->notification()->warning(
                    title: __('Duplicate Vehicle'),
                    description: __('This vehicle is already in another prealert.')
                );
                return;
            }
        }

        $prealert = Prealert::create([
            'shipper_id' => $this->shipper_id,
            'consignee_id' => $this->consignee_id,
            'vin' => $this->vin,
            'vehicle_id' => $this->vehicle?->id,
            'carrier_id' => (int) $this->carrier_id ?: null,
            'destination_port_id' => (int) $this->destination_port_id ?: null,
            'gatepass_pin' => $this->gatepass_pin,
            'auction_receipt' => $path,
            'notes' => $this->notes,
        ]);

        // Identify all non-shipper administrative staff roles
        $adminRoleNames = Role::query()
            ->where('name', '!=', 'shipper')
            ->pluck('name');

        // Identify all recipients (Non-shipper roles, staff, and the specific shipper owner)
        $recipientIds = User::query()
            ->role($adminRoleNames)
            ->pluck('id')
            ->merge(User::query()->whereHas('staff')->pluck('id'))
            ->when($prealert->shipper?->user_id, fn ($q) => $q->push($prealert->shipper->user_id))
            ->unique()
            ->values();


        $recipients = User::query()->whereIn('id', $recipientIds)->get();

        if ($recipients->isNotEmpty()) {
            \Illuminate\Support\Facades\Notification::send($recipients, new PrealertCreatedNotification($prealert));
        }


        $this->dialog()->show([
            'icon' => 'success',
            'title' => __('Success!'),
            'description' => __('Prealert submitted successfully.'),
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
}; ?>

<div>
    <x-crud.page-shell>
        <div class="max-w-4xl mx-auto">
            <div class="flex items-center gap-3 mb-8">
                <flux:button variant="ghost" icon="arrow-left" :href="route('prealerts.index')" wire:navigate />
                <x-crud.page-header :heading="__('Submit Prealert')" :subheading="__('Enter vehicle details and documentation to begin the shipping process.')" class="!mb-0" />
            </div>

            <form wire:submit="save" class="space-y-6">
                <x-crud.panel class="p-6">
                    <div class="space-y-6">
                        {{-- VIN & Lookup --}}
                        <div
                            class="bg-white dark:bg-zinc-800 rounded-xl p-6 border border-zinc-200 dark:border-zinc-700 shadow-sm transition-all @if($vehicle) ring-2 ring-green-500/20 border-green-500/30 @endif">
                            <flux:field>
                                <flux:label size="lg" class="mb-2 font-bold">{{ __('Enter Vehicle VIN') }}</flux:label>
                                <flux:input wire:model.live.debounce.500ms="vin"
                                    placeholder="{{ __('17-character VIN') }}" maxlength="17" icon="identification"
                                    size="lg" :disabled="$loadingVehicle" class="font-mono uppercase text-lg">
                                    <x-slot name="append">
                                        @if($loadingVehicle)
                                            <flux:icon.arrow-path class="size-5 animate-spin text-zinc-400" />
                                        @elseif($vehicle)
                                            <flux:icon.check-circle class="size-5 text-green-500" />
                                        @endif
                                    </x-slot>
                                </flux:input>
                                @if($vinError)
                                    <flux:error>{{ $vinError }}</flux:error>
                                @endif
                                <flux:description>
                                    {{ __('Type the 17-character VIN to automatically fetch vehicle details.') }}
                                </flux:description>
                            </flux:field>
                        </div>

                        {{-- Vehicle Summary Card (Conditional) --}}
                        @if($vehicle)
                            <div
                                class="bg-white dark:bg-zinc-800/50 rounded-lg border border-zinc-200 dark:border-zinc-700 animate-in fade-in slide-in-from-top-2 overflow-hidden flex flex-col lg:flex-row shadow-sm">
                                {{-- Photo Slider --}}
                                <div class="lg:w-[45%] w-full bg-zinc-100 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700 relative min-h-[300px] lg:min-h-full"
                                    x-data="{ activeSlide: 0, slides: @js($vehicle->copartCarPhotoUrls()) }">

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
                                <div class="lg:w-[55%] w-full p-4 lg:p-5">
                                    <div class="flex items-start justify-between mb-4">
                                        <div>
                                            <h4 class="font-bold text-xl text-zinc-900 dark:text-white leading-tight">
                                                {{ $vehicle->year }} {{ $vehicle->make }} {{ $vehicle->model }}
                                            </h4>
                                            <p class="text-[8px] text-zinc-500 mt-1 uppercase tracking-widest font-bold">
                                                {{ __('Vehicle Identification (VIN)') }}
                                            </p>
                                            <p
                                                class="font-mono text-zinc-700 dark:text-zinc-300 font-medium text-sm mt-0.5">
                                                {{ $vehicle->vin }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-y-4 gap-x-4 text-xs">
                                        <div>
                                            <p class="text-[10px] text-zinc-500 font-bold uppercase tracking-widest mb-1">
                                                {{ __('Color') }}</p>
                                            <p
                                                class="font-medium text-zinc-900 dark:text-zinc-100 flex items-center gap-1.5">
                                                @if($vehicle->color)
                                                    <span
                                                        class="w-2.5 h-2.5 rounded-full border border-zinc-300 dark:border-zinc-600 shadow-sm"
                                                        style="background-color: {{ strtolower($vehicle->color ?? '') === 'charcoal' ? '#36454F' : (strtolower($vehicle->color ?? '') === 'grey' || strtolower($vehicle->color ?? '') === 'gray' ? '#808080' : (strtolower($vehicle->color ?? '') === 'black' ? '#000000' : (strtolower($vehicle->color ?? '') === 'white' ? '#FFFFFF' : (strtolower($vehicle->color ?? '') === 'silver' ? '#C0C0C0' : (strtolower($vehicle->color ?? '') === 'red' ? '#FF0000' : (strtolower($vehicle->color ?? '') === 'blue' ? '#0000FF' : 'transparent')))))) }};"></span>
                                                @endif
                                                {{ $vehicle->color ?: '—' }}
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] text-zinc-500 font-bold uppercase tracking-widest mb-1">
                                                {{ __('Car Keys') }}</p>
                                            <p class="font-medium text-zinc-900 dark:text-zinc-100">
                                                @if($vehicle->car_keys === '1')
                                                    <span class="text-green-600 dark:text-green-400 flex items-center gap-1">
                                                        <flux:icon.key class="size-3.5" /> {{ __('Yes') }}
                                                    </span>
                                                @elseif($vehicle->car_keys === '0')
                                                    <span class="text-red-500 flex items-center gap-1"><flux:icon.x-mark
                                                            class="size-3.5" /> {{ __('No') }}</span>
                                                @else
                                                    —
                                                @endif
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] text-zinc-500 font-bold uppercase tracking-widest mb-1">
                                                {{ __('Purchase Price') }}</p>
                                            <p class="font-medium text-zinc-900 dark:text-zinc-100 text-sm">
                                                @if($vehicle->est_retail_value)
                                                    <span
                                                        class="text-green-700 dark:text-green-400">${{ number_format((float) $vehicle->est_retail_value) }}</span>
                                                @else
                                                    —
                                                @endif
                                            </p>
                                        </div>
                                        <div
                                            class="col-span-2 sm:col-span-1 border-t border-zinc-100 dark:border-zinc-700/50 pt-2 sm:border-t-0 sm:pt-0">
                                            <p class="text-[10px] text-zinc-500 font-bold uppercase tracking-widest mb-1">
                                                {{ __('Primary Damage') }}</p>
                                            <p class="font-medium text-red-600 dark:text-red-400 break-words">
                                                {{ $vehicle->primary_damage ?: '—' }}</p>
                                        </div>
                                        <div
                                            class="col-span-2 sm:col-span-2 border-t border-zinc-100 dark:border-zinc-700/50 pt-2 sm:border-t-0 sm:pt-0">
                                            <p class="text-[10px] text-zinc-500 font-bold uppercase tracking-widest mb-1">
                                                {{ __('Secondary Damage') }}</p>
                                            <p class="font-medium text-orange-600 dark:text-orange-400 break-words">
                                                {{ $vehicle->secondary_damage ?: '—' }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Additional Fields --}}
                            <div
                                class="grid grid-cols-1 md:grid-cols-2 gap-6 animate-in fade-in slide-in-from-top-4 duration-500">
                                {{-- Shipper & Consignee --}}
                                <div class="md:col-span-2 flex flex-col gap-6">
                                    <div>
                                        @if (Auth::user()?->hasRole('super_admin') || Auth::user()?->staff()->exists())
                                            <x-select wire:model.live="shipper_id" :label="__('Shipper')"
                                                :placeholder="__('Search and select shipper')" option-value="id"
                                                option-label="name" :async-data="route('api.shippers.search')" searchable
                                                required />
                                        @else
                                            <flux:input :label="__('Shipper')"
                                                :value="sprintf(
                                                    '%s(%s)',
                                                    Auth::user()?->name ?? '',
                                                    Auth::user()?->shipper?->company_name ?? '-'
                                                )"
                                                disabled />
                                            <input type="hidden" wire:model="shipper_id">
                                        @endif
                                    </div>

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
                                                                    class="ml-2 text-[10px] font-bold uppercase tracking-widest text-zinc-400">({{ __('Default') }})</span>
                                                            @endif
                                                        </flux:select.option>
                                                    @endforeach
                                                </flux:select>
                                            </div>
                                            @if($shipper_id)
                                                <flux:button type="button" wire:click="$set('showConsigneeModal', true)"
                                                    icon="plus" class="shrink-0">{{ __('New') }}</flux:button>
                                            @endif
                                        </div>
                                        <flux:error name="consignee_id" />
                                    </flux:field>
                                </div>

                                <div class="md:col-span-2">
                                    <flux:input wire:model="gatepass_pin" :label="__('Gatepass PIN (Optional)')"
                                        placeholder="{{ __('Max 11 characters') }}" maxlength="11" icon="key" />
                                </div>

                                <flux:separator class="md:col-span-2 my-2" />
 
                                <x-select
                                    wire:model.live="carrier_id"
                                    :label="__('Carrier')"
                                    :placeholder="__('Select carrier')"
                                    :options="$this->carriers"
                                    option-label="name"
                                    option-value="id"
                                    icon="truck"
                                />

                                <x-select
                                    wire:model.live="destination_port_id"
                                    :label="__('Destination Port')"
                                    :placeholder="__('Select destination port')"
                                    :options="$this->ports"
                                    option-label="name"
                                    option-value="id"
                                    icon="map-pin"
                                />

                                <div class="md:col-span-2">
                                    <flux:field>
                                        <flux:label>{{ __('Action Receipt') }}</flux:label>
                                        <div x-data="{ isDragging: false }" @dragover.prevent="isDragging = true"
                                            @dragleave.prevent="isDragging = false"
                                            @drop.prevent="isDragging = false; @this.upload('auction_receipt', event.dataTransfer.files[0])"
                                            :class="{ 'border-zinc-900 dark:border-white bg-zinc-50 dark:bg-zinc-800/40': isDragging }"
                                            class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-zinc-300 dark:border-zinc-700 border-dashed rounded-xl transition-all">
                                            <div class="space-y-2 text-center">
                                                <div class="flex flex-col items-center">
                                                    @if($auction_receipt && method_exists($auction_receipt, 'isPreviewable') && $auction_receipt->isPreviewable())
                                                        <div class="relative group">
                                                            <img src="{{ $auction_receipt->temporaryUrl() }}"
                                                                class="h-32 w-auto rounded-lg shadow-md mb-2">
                                                            <button type="button" wire:click="$set('auction_receipt', null)"
                                                                class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 shadow-lg opacity-0 group-hover:opacity-100 transition-opacity">
                                                                <flux:icon.x-mark class="size-4" />
                                                            </button>
                                                        </div>
                                                    @elseif($auction_receipt)
                                                        <div
                                                            class="flex items-center gap-2 p-2 bg-zinc-100 dark:bg-zinc-800 rounded-lg mb-2">
                                                            <flux:icon.document class="size-6 text-zinc-500" />
                                                            <span
                                                                class="text-xs font-medium">{{ $auction_receipt->getClientOriginalName() }}</span>
                                                        </div>
                                                    @else
                                                        <flux:icon.document-arrow-up class="mx-auto size-12 text-zinc-400" />
                                                    @endif
                                                    <div class="flex text-sm text-zinc-600 dark:text-zinc-400">
                                                        <label for="receipt-upload"
                                                            class="relative cursor-pointer font-semibold text-zinc-900 dark:text-white hover:underline focus-within:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-zinc-500">
                                                            <span>{{ __('Upload a file') }}</span>
                                                            <input id="receipt-upload" name="receipt-upload" type="file"
                                                                wire:model="auction_receipt" class="sr-only">
                                                        </label>
                                                        <p class="pl-1">{{ __('or drag and drop') }}</p>
                                                    </div>
                                                    <p class="text-xs text-zinc-500">
                                                        PNG, JPG or PDF up to 5MB
                                                    </p>
                                                </div>
                                                <div wire:loading wire:target="auction_receipt"
                                                    class="text-sm text-zinc-500 animate-pulse">
                                                    {{ __('Uploading...') }}
                                                </div>
                                            </div>
                                        </div>
                                        <flux:error name="auction_receipt" />
                                    </flux:field>
                                </div>

                                <div class="md:col-span-2">
                                    <flux:textarea wire:model="notes" :label="__('Notes (Optional)')" rows="3"
                                        placeholder="{{ __('Any additional information...') }}" />
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-3 mt-8">
                                <flux:button variant="ghost" :href="route('prealerts.index')" wire:navigate>
                                    {{ __('Cancel') }}</flux:button>
                                <flux:button variant="primary" type="submit" :disabled="$loadingVehicle"
                                    wire:loading.attr="disabled">
                                    {{ __('Submit Prealert') }}
                                </flux:button>
                            </div>
                        @endif
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