<?php

declare(strict_types=1);

use App\Models\Carrier;
use App\Models\DefaultShipmentSetting;
use App\Models\PaymentMethod;
use App\Models\Port;
use Livewire\Attributes\Title;
use Livewire\Component;
use WireUi\Traits\WireUiActions;

new #[Title('Default Shipment Settings')] class extends Component {
    use WireUiActions;

    public ?int $carrier_id = null;
    public ?int $origin_port_id = null;
    public ?string $logistics_service = null;
    public ?string $shipping_mode = null;
    public ?string $shipment_status = null;
    public ?string $invoice_status = null;
    public ?string $payment_status = null;
    public ?int $payment_method_id = null;

    public function mount(): void
    {
        $this->authorize('default_shipment_settings.view');

        $setting = DefaultShipmentSetting::current();

        $this->carrier_id = $setting->carrier_id;
        $this->origin_port_id = $setting->origin_port_id;
        $this->logistics_service = $setting->logistics_service?->value;
        $this->shipping_mode = $setting->shipping_mode?->value;
        $this->shipment_status = $setting->shipment_status?->value;
        $this->invoice_status = $setting->invoice_status?->value;
        $this->payment_status = $setting->payment_status?->value;
    }

    public function save(): void
    {
        $this->authorize('default_shipment_settings.update');

        $validated = $this->validate([
            'carrier_id' => 'nullable|exists:carriers,id',
            'origin_port_id' => 'nullable|exists:ports,id',
            'logistics_service' => 'nullable|string',
            'shipping_mode' => 'nullable|string',
            'shipment_status' => 'nullable|string',
            'invoice_status' => 'nullable|string',
            'payment_status' => 'nullable|string',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
        ]);

        $setting = DefaultShipmentSetting::current();
        $setting->update($validated);

        $this->notification()->success(__('Settings updated successfully.'));
    }

    public function with(): array
    {
        $ports = Port::where('type', 'origin')
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
            
        return [
            'carriers' => Carrier::orderBy('name')->get(),
            'ports' => $ports,
            'payment_methods' => PaymentMethod::query()->orderBy('name')->get(),
        ];
    }
}; ?>

<div>
    <x-crud.page-shell>
        <div class="flex items-center justify-between mb-8">
            <x-crud.page-header :heading="__('Default Shipment Settings')" :subheading="__('Manage global default settings applied to new shipments.')" icon="cog" class="!mb-0" />
        </div>

        <x-crud.panel class="p-6">
            <form wire:submit="save" class="space-y-8">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <flux:field>
                        <flux:label icon="truck">{{ __('Default Carrier') }}</flux:label>
                        <flux:select wire:model="carrier_id" placeholder="Choose a carrier...">
                            <flux:select.option value="">{{ __('None') }}</flux:select.option>
                            @foreach ($carriers as $carrier)
                                <flux:select.option value="{{ $carrier->id }}">{{ $carrier->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>

                    <flux:field>
                        <flux:label icon="anchor">{{ __('Default Origin Port') }}</flux:label>
                        <flux:select wire:model="origin_port_id" placeholder="Choose an origin port...">
                            <flux:select.option value="">{{ __('None') }}</flux:select.option>
                            @foreach ($ports as $port)
                                <flux:select.option value="{{ $port->id }}">{{ $port->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>

                    <flux:field>
                        <flux:label icon="cog-6-tooth">{{ __('Logistics Service') }}</flux:label>
                        <flux:select wire:model="logistics_service" placeholder="Choose a logistics service...">
                            <flux:select.option value="">{{ __('None') }}</flux:select.option>
                            @foreach (App\Enums\LogisticsService::cases() as $case)
                                <flux:select.option value="{{ $case->value }}">{{ str($case->name)->headline() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>

                    <flux:field>
                        <flux:label icon="paper-airplane">{{ __('Shipping Mode') }}</flux:label>
                        <flux:select wire:model="shipping_mode" placeholder="Choose a shipping mode...">
                            <flux:select.option value="">{{ __('None') }}</flux:select.option>
                            @foreach (App\Enums\ShippingMode::cases() as $case)
                                <flux:select.option value="{{ $case->value }}">{{ str($case->name)->headline() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>

                    <flux:field>
                        <flux:label icon="check-circle">{{ __('Shipment Status') }}</flux:label>
                        <flux:select wire:model="shipment_status" placeholder="Choose an initial status...">
                            <flux:select.option value="">{{ __('None') }}</flux:select.option>
                            @foreach (App\Enums\ShipmentStatus::cases() as $case)
                                <flux:select.option value="{{ $case->value }}">{{ str($case->name)->headline() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>

                    <flux:field>
                        <flux:label icon="document-text">{{ __('Invoice Status') }}</flux:label>
                        <flux:select wire:model="invoice_status" placeholder="Choose an initial invoice status...">
                            <flux:select.option value="">{{ __('None') }}</flux:select.option>
                            @foreach (App\Enums\InvoiceStatus::cases() as $case)
                                <flux:select.option value="{{ $case->value }}">{{ str($case->name)->headline() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>

                    <flux:field>
                        <flux:label icon="credit-card">{{ __('Payment Status') }}</flux:label>
                        <flux:select wire:model="payment_status" placeholder="Choose an initial payment status...">
                            <flux:select.option value="">{{ __('None') }}</flux:select.option>
                            @foreach (App\Enums\PaymentStatus::cases() as $case)
                                <flux:select.option value="{{ $case->value }}">{{ str($case->name)->headline() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>

                    <flux:field>
                        <flux:label icon="banknotes">{{ __('Default payment method') }}</flux:label>
                        <flux:select wire:model="payment_method_id" placeholder="{{ __('Choose a payment method…') }}">
                            <flux:select.option value="">{{ __('None') }}</flux:select.option>
                            @foreach ($payment_methods as $method)
                                <flux:select.option value="{{ $method->id }}">{{ $method->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>
                </div>

                <div class="flex justify-end pt-4 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button type="submit" variant="primary" icon="check">{{ __('Save Settings') }}</flux:button>
                </div>
            </form>
        </x-crud.panel>
    </x-crud.page-shell>
</div>
d.page-shell>
</div>
