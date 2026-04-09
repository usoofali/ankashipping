<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\LogisticsService;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Enums\ShippingMode;
use App\Models\ActivityLog;
use App\Models\Carrier;
use App\Models\Consignee;
use App\Models\PaymentMethod;
use App\Models\Port;
use App\Models\Shipment;
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
    public string $vin = '';
    public ?string $gatepass_pin = '';
    public ?int $carrier_id = null;
    public ?int $origin_port_id = null;
    public ?int $destination_port_id = null;
    public string $logistics_service = '';
    public string $shipping_mode = '';
    public string $shipment_status = '';
    public string $invoice_status = '';
    public string $payment_status = '';
    public ?int $payment_method_id = null;
    public ?string $auction_receipt = '';

    public function mount(Shipment $shipment): void
    {
        $this->authorize('shipments.update');

        $this->shipment = $shipment->load([
            'shipper.user',
            'consignee',
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
        $this->vin = (string) ($shipment->vin ?? '');
        $this->gatepass_pin = $shipment->gatepass_pin;
        $this->carrier_id = $shipment->carrier_id;
        $this->origin_port_id = $shipment->origin_port_id;
        $this->destination_port_id = $shipment->destination_port_id;
        $this->auction_receipt = $shipment->auction_receipt;
        $this->logistics_service = (string) ($shipment->logistics_service?->value ?? $shipment->logistics_service ?? '');
        $this->shipping_mode = (string) ($shipment->shipping_mode?->value ?? $shipment->shipping_mode ?? '');
        $this->shipment_status = (string) ($shipment->shipment_status?->value ?? $shipment->shipment_status ?? '');
        $this->invoice_status = (string) ($shipment->invoice_status?->value ?? $shipment->invoice_status ?? '');
        $this->payment_status = (string) ($shipment->payment_status?->value ?? $shipment->payment_status ?? '');
        $this->payment_method_id = $shipment->payment_method_id;
    }

    public function updatedShipperId(): void
    {
        $this->consignee_id = null;
    }

    public function save(): void
    {
        $this->authorize('shipments.update');

        $validated = $this->validate([
            'reference_no' => ['required', 'string', 'max:255', 'unique:shipments,reference_no,'.$this->shipment->id],
            'shipper_id' => ['required', 'exists:shippers,id'],
            'consignee_id' => ['required', 'exists:consignees,id'],
            'vin' => ['required', 'string', 'size:17', 'unique:shipments,vin,'.$this->shipment->id],
            'gatepass_pin' => ['nullable', 'string', 'max:11'],
            'carrier_id' => ['nullable', 'exists:carriers,id'],
            'origin_port_id' => ['nullable', 'exists:ports,id'],
            'destination_port_id' => ['nullable', 'exists:ports,id'],
            'logistics_service' => ['required', 'string'],
            'shipping_mode' => ['required', 'string'],
            'shipment_status' => ['required', 'string'],
            'invoice_status' => ['required', 'string'],
            'payment_status' => ['required', 'string'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
        ]);

        $this->shipment->update($validated);

        if ($this->shipment->invoice) {
            $this->shipment->invoice->update([
                'status' => $this->invoice_status,
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
        if (! $this->shipper_id) {
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
    <div class="max-w-5xl mx-auto space-y-6">
        <div class="flex items-center gap-3">
            <flux:button variant="ghost" icon="arrow-left" :href="route('shipments.show', $shipment)" wire:navigate />
            <x-crud.page-header :heading="__('Edit Shipment')" :subheading="__('Update shipment routing, statuses, and assignment details.')" class="!mb-0" />
        </div>

        <form wire:submit="save" class="space-y-6">
            <x-crud.panel class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <flux:input wire:model="reference_no" :label="__('Reference Number')" icon="qr-code" disabled />
                    <flux:input wire:model="vin" :label="__('VIN')" maxlength="17" icon="identification" class="font-mono uppercase" disabled />

                    <x-select
                        wire:model.live="shipper_id"
                        :label="__('Shipper')"
                        :placeholder="__('Search and select shipper')"
                        option-value="id"
                        option-label="name"
                        :async-data="route('api.shippers.search')"
                        searchable
                        required
                        disabled
                    />

                    <flux:select wire:model="consignee_id" :label="__('Consignee')" :placeholder="__('Select consignee')">
                        @foreach($this->consignees as $consignee)
                            <flux:select.option :value="$consignee->id">
                                {{ $consignee->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="gatepass_pin" :label="__('Gatepass PIN')" maxlength="11" icon="key" />

                    <flux:select wire:model="carrier_id" :label="__('Carrier')" :placeholder="__('Select carrier')">
                        <flux:select.option value="">{{ __('Select carrier') }}</flux:select.option>
                        @foreach($this->carriers as $carrier)
                            <flux:select.option :value="$carrier->id">{{ $carrier->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </x-crud.panel>

            <x-crud.panel class="p-6">
                <flux:heading size="lg" class="mb-4">{{ __('Routing & Service') }}</flux:heading>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <flux:select wire:model="origin_port_id" :label="__('Origin Port')" icon="map-pin">
                        <flux:select.option value="">{{ __('Select port') }}</flux:select.option>
                        @foreach ($this->shipmentOriginPorts as $port)
                            <flux:select.option :value="$port->id">{{ $port->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="destination_port_id" :label="__('Destination Port')" icon="flag">
                        <flux:select.option value="">{{ __('Select port') }}</flux:select.option>
                        @foreach ($this->shipmentDestinationPorts as $port)
                            <flux:select.option :value="$port->id">{{ $port->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="logistics_service" :label="__('Logistics Service')">
                        @foreach(LogisticsService::cases() as $service)
                            <flux:select.option value="{{ $service->value }}">{{ $service->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="shipping_mode" :label="__('Shipping Mode')">
                        @foreach(ShippingMode::cases() as $mode)
                            <flux:select.option value="{{ $mode->value }}">{{ $mode->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </x-crud.panel>

            <x-crud.panel class="p-6">
                <flux:heading size="lg" class="mb-4">{{ __('Workflow Status') }}</flux:heading>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <flux:select wire:model="shipment_status" :label="__('Shipment Status')">
                        @foreach(ShipmentStatus::cases() as $status)
                            <flux:select.option value="{{ $status->value }}">{{ $status->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="invoice_status" :label="__('Invoice Status')">
                        @foreach(InvoiceStatus::cases() as $status)
                            <flux:select.option value="{{ $status->value }}">{{ $status->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="payment_status" :label="__('Payment Status')">
                        @foreach(PaymentStatus::cases() as $status)
                            <flux:select.option value="{{ $status->value }}">{{ $status->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="payment_method_id" :label="__('Payment method')" icon="banknotes">
                        <flux:select.option value="">{{ __('None') }}</flux:select.option>
                        @foreach(PaymentMethod::query()->orderBy('name')->get() as $method)
                            <flux:select.option value="{{ $method->id }}">{{ $method->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </x-crud.panel>

            <div class="flex items-center justify-end gap-3">
                <flux:button variant="ghost" :href="route('shipments.show', $shipment)" wire:navigate>{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" type="submit">{{ __('Save Changes') }}</flux:button>
            </div>
        </form>
    </div>
</x-crud.page-shell>
