<?php

declare(strict_types=1);

use App\Enums\PrealertStatus;
use App\Models\Carrier;
use App\Models\Consignee;
use App\Models\Port;
use App\Models\Prealert;
use App\Models\Vehicle;
use App\Services\VinLookupService;
use App\Enums\VinLookupOutcome;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use WireUi\Traits\WireUiActions;

new #[Title('Edit Prealert')] class extends Component {
    use WithFileUploads;
    use WireUiActions;

    public Prealert $prealert;

    public ?int $shipper_id = null;
    public ?int $consignee_id = null;
    public string $vin = '';
    public string $gatepass_pin = '';
    public ?int $carrier_id = null;
    public ?int $destination_port_id = null;
    public $auction_receipt;
    public ?string $existingActionReceipt = null;
    public string $notes = '';

    public ?Vehicle $vehicle = null;
    public bool $loadingVehicle = false;
    public ?string $vinError = null;

    public function mount(Prealert $prealert): void
    {
        $this->prealert = $prealert;

        // Authorization check
        $user = Auth::user();
        $isStaff = $user?->hasRole('super_admin') || $user?->staff()->exists();

        if (! $isStaff && $prealert->shipper_id !== $user?->shipper?->id) {
            abort(403);
        }

        if (! $isStaff && ! in_array($prealert->status, [PrealertStatus::Draft, PrealertStatus::Rejected])) {
             // Shippers can only edit if Draft or Rejected
             abort(403, __('You cannot edit a prealert that has already been submitted or approved.'));
        }

        $this->shipper_id = $prealert->shipper_id;
        $this->consignee_id = $prealert->consignee_id;
        $this->vin = $prealert->vin;
        $this->gatepass_pin = $prealert->gatepass_pin ?? '';
        $this->carrier_id = $prealert->carrier_id;
        $this->destination_port_id = $prealert->destination_port_id;
        $this->existingActionReceipt = $prealert->auction_receipt;
        $this->notes = $prealert->notes ?? '';
        $this->vehicle = $prealert->vehicle;
    }

    public function updatedShipperId(): void
    {
        $this->consignee_id = null;
        if ($this->shipper_id) {
            $default = Consignee::where('shipper_id', $this->shipper_id)
                ->where('is_default', true)
                ->first();

            if ($default) {
                $this->consignee_id = $default->id;
            }
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

        if (! $this->shipper_id && ! $isAdminOrStaff) {
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
            } else {
                $this->vinError = $result->message;
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

        $data = [
            'shipper_id' => $this->shipper_id,
            'consignee_id' => $this->consignee_id,
            'vin' => $this->vin,
            'vehicle_id' => $this->vehicle?->id,
            'destination_port_id' => (int) $this->destination_port_id ?: null,
            'carrier_id' => (int) $this->carrier_id ?: null,
            'gatepass_pin' => $this->gatepass_pin,
            'notes' => $this->notes,
        ];

        if ($this->auction_receipt) {
            // Delete old one if exists
            if ($this->existingActionReceipt) {
                Storage::disk('public')->delete($this->existingActionReceipt);
            }
            $data['auction_receipt'] = $this->auction_receipt->store('prealerts/receipts', 'public');
        }

        if ($this->vehicle?->id) {
            $exists = Prealert::where('vehicle_id', $this->vehicle->id)
                ->where('id', '!=', $this->prealert->id)
                ->exists();
            if ($exists) {
                $this->notification()->warning(
                    title: __('Duplicate Vehicle'),
                    description: __('This vehicle is already in another prealert.')
                );
                return;
            }
        }

        $this->prealert->update($data);

        $this->notification()->success(
            title: __('Success'),
            description: __('Prealert updated successfully.')
        );

        $this->redirect(route('prealerts.index'), navigate: true);
    }

    #[Computed]
    public function carriers()
    {
        return Carrier::orderBy('name')->get();
    }

    #[Computed]
    public function ports()
    {
        return Port::where('type', 'destination')->orderBy('name')->get();
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
}; ?>

<x-crud.page-shell>
    <div class="max-w-4xl mx-auto">
        <div class="flex items-center gap-3 mb-8">
            <flux:button variant="ghost" icon="arrow-left" :href="route('prealerts.index')" wire:navigate />
            <x-crud.page-header :heading="__('Edit Prealert')" :subheading="__('Update vehicle details or documentation for this prealert.')" class="!mb-0" />
        </div>

        <form wire:submit="save" class="space-y-6">
            <x-crud.panel class="p-6">
                <div class="space-y-6">
                    {{-- VIN & Lookup --}}
                    <div class="bg-white dark:bg-zinc-800 rounded-xl p-6 border border-zinc-200 dark:border-zinc-700 shadow-sm transition-all @if($vehicle) ring-2 ring-green-500/20 border-green-500/30 @endif">
                        <flux:field>
                            <flux:label size="lg" class="mb-2 font-bold">{{ __('Vehicle VIN') }}</flux:label>
                            <flux:input
                                wire:model.live.debounce.500ms="vin"
                                placeholder="{{ __('17-character VIN') }}"
                                maxlength="17"
                                icon="identification"
                                size="lg"
                                :disabled="$loadingVehicle"
                                class="font-mono uppercase text-lg"
                            >
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
                            <flux:description>{{ __('Type the 17-character VIN to refresh vehicle details.') }}</flux:description>
                        </flux:field>
                    </div>

                    {{-- Vehicle Summary Card --}}
                    @if($vehicle)
                        <div class="bg-zinc-50 dark:bg-zinc-800/50 rounded-xl p-4 border border-zinc-200 dark:border-zinc-700 animate-in fade-in slide-in-from-top-2">
                            <div class="flex items-start gap-4">
                                <div class="bg-white dark:bg-zinc-800 rounded-lg p-3 shadow-sm border border-zinc-200 dark:border-zinc-700">
                                    <flux:icon.truck class="size-10 text-zinc-600 dark:text-zinc-400" />
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-bold text-xl text-zinc-900 dark:text-white">
                                        {{ $vehicle->year }} {{ $vehicle->make }} {{ $vehicle->model }}
                                    </h4>
                                    <p class="text-xs text-zinc-500 mt-1 uppercase tracking-wider">
                                        {{ __('Series') }}: {{ $vehicle->series ?: '—' }} | {{ __('Type') }}: {{ $vehicle->vehicle_type ?: '—' }}
                                    </p>
                                    <div class="mt-3 flex flex-wrap gap-2 text-[10px] font-bold uppercase tracking-widest text-zinc-500">
                                        <span class="bg-zinc-200 dark:bg-zinc-700 px-2 py-0.5 rounded">{{ $vehicle->color ?: __('Color N/A') }}</span>
                                        <span class="bg-zinc-200 dark:bg-zinc-700 px-2 py-0.5 rounded">{{ $vehicle->fuel ?: __('Fuel N/A') }}</span>
                                        <span class="bg-zinc-200 dark:bg-zinc-700 px-2 py-0.5 rounded">{{ $vehicle->engine_type ?: __('Engine N/A') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Additional Fields --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 animate-in fade-in slide-in-from-top-4 duration-500">
                        {{-- Shipper & Consignee --}}
                        <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                @if (Auth::user()?->hasRole('super_admin') || Auth::user()?->staff()->exists())
                                    <x-select
                                        wire:model.live="shipper_id"
                                        :label="__('Shipper')"
                                        :placeholder="__('Search and select shipper')"
                                        option-value="id"
                                        option-label="name"
                                        :async-data="route('api.shippers.search')"
                                        searchable
                                        required
                                    />
                                @else
                                    <flux:input :label="__('Shipper')" :value="$prealert->shipper?->company_name ?: $prealert->shipper?->user?->name" disabled />
                                @endif
                            </div>

                            <div>
                                <flux:select
                                    wire:model="consignee_id"
                                    :label="__('Consignee')"
                                    :placeholder="__('Select consignee')"
                                >
                                    @foreach($this->consignees as $consignee)
                                        <flux:select.option :value="$consignee->id">
                                            {{ $consignee->name }}
                                            @if($consignee->is_default)
                                                <span class="ml-2 text-[10px] font-bold uppercase tracking-widest text-zinc-400">({{ __('Default') }})</span>
                                            @endif
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <flux:input
                                wire:model="gatepass_pin"
                                :label="__('Gatepass PIN (Optional)')"
                                placeholder="{{ __('Max 11 characters') }}"
                                maxlength="11"
                                icon="key"
                            />
                        </div>

                        <flux:separator class="md:col-span-2 my-2" />

                        <x-select
                            wire:model.live="carrier_id"
                            :label="__('Carrier')"
                            :placeholder="__('Select carrier')"
                            :options="$this->carriers"
                            option-label="name"
                            option-value="id"
                        />

                        <x-select
                            wire:model.live="destination_port_id"
                            :label="__('Destination Port')"
                            :placeholder="__('Select destination port')"
                            :options="$this->ports"
                            option-label="name"
                            option-value="id"
                        />

                        <div class="md:col-span-2">
                            <flux:field>
                                <flux:label>{{ __('Action Receipt') }}</flux:label>
                                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-zinc-300 dark:border-zinc-700 border-dashed rounded-xl transition-all">
                                    <div class="space-y-2 text-center text-sm text-zinc-600 dark:text-zinc-400 w-full">
                                        <div class="flex flex-col items-center">
                                            @if($auction_receipt && method_exists($auction_receipt, 'isPreviewable') && $auction_receipt->isPreviewable())
                                                <div class="relative group">
                                                    <img src="{{ $auction_receipt->temporaryUrl() }}" class="h-32 w-auto rounded-lg shadow-md mb-2">
                                                    <button type="button" wire:click="$set('auction_receipt', null)" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 shadow-lg opacity-0 group-hover:opacity-100 transition-opacity">
                                                        <flux:icon.x-mark class="size-4" />
                                                    </button>
                                                </div>
                                            @elseif($auction_receipt)
                                                <div class="flex items-center gap-2 p-2 bg-zinc-100 dark:bg-zinc-800 rounded-lg mb-2">
                                                    <flux:icon.document class="size-6 text-zinc-500" />
                                                    <span class="text-xs font-medium">{{ $auction_receipt->getClientOriginalName() }}</span>
                                                </div>
                                            @elseif($existingActionReceipt)
                                                <div class="relative group">
                                                    <img src="{{ Storage::url($existingActionReceipt) }}" class="h-48 w-full object-cover rounded-lg shadow-md transition-all group-hover:brightness-50">
                                                    <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                                        <flux:link :href="Storage::url($existingActionReceipt)" target="_blank" size="sm" icon="magnifying-glass-plus" class="text-white no-underline">{{ __('View Current') }}</flux:link>
                                                    </div>
                                                </div>
                                            @else
                                                <flux:icon.document-arrow-up class="mx-auto size-12 text-zinc-400" />
                                            @endif

                                            <div class="mt-4 flex flex-col items-center">
                                                <label for="receipt-upload" class="relative cursor-pointer font-bold text-zinc-900 dark:text-white hover:underline bg-zinc-100 dark:bg-zinc-800 px-4 py-2 rounded-lg border border-zinc-200 dark:border-zinc-700 shadow-sm transition-all">
                                                    <span>{{ $existingActionReceipt ? __('Replace Receipt') : __('Upload Receipt') }}</span>
                                                    <input id="receipt-upload" name="receipt-upload" type="file" wire:model="auction_receipt" class="sr-only">
                                                </label>
                                                <p class="mt-2 text-xs text-zinc-500 italic">{{ __('PDF, JPEG, or PNG up to 5MB') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </flux:error>
                            <flux:error name="auction_receipt" />
                        </div>

                        <div class="md:col-span-2">
                            <flux:textarea wire:model="notes" :label="__('Notes (Optional)')" rows="3" placeholder="{{ __('Any additional information...') }}" />
                        </div>
                    </div>
                </div>
            </x-crud.panel>

            <div class="flex items-center justify-end gap-3 mt-6">
                <flux:button variant="ghost" :href="route('prealerts.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit" :disabled="$loadingVehicle" wire:loading.attr="disabled">
                    {{ __('Save Changes') }}
                </flux:button>
            </div>
        </form>
    </div>
</x-crud.page-shell>
