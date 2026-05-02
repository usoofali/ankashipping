<?php

declare(strict_types=1);

use App\Enums\ShipmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\ShippingMode;
use App\Models\Shipment;
use App\Models\Prealert;
use App\Models\Invoice;
use App\Models\WalletTopUp;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('My Dashboard')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $filterMonth = '';
    public string $filterYear = '';
    public string $activeTab = 'shipments';

    public function mount(): void
    {
        $this->filterMonth = (string) now()->month;
        $this->filterYear = (string) now()->year;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'filterMonth', 'filterYear'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function stats(): array
    {
        $shipperId = auth()->user()->shipper?->id;

        if (!$shipperId) {
            return [];
        }

        $deliveredStatuses = [ShipmentStatus::Delivered, ShipmentStatus::Loaded, ShipmentStatus::Completed];
        $undeliveredStatuses = [ShipmentStatus::Open, ShipmentStatus::Pending, ShipmentStatus::Dispatched, ShipmentStatus::Booking, ShipmentStatus::Inland];

        return [
            'total_shipments' => Shipment::where('shipper_id', $shipperId)->count(),
            'total_prealerts' => Prealert::where('shipper_id', $shipperId)->count(),
            'delivered' => Shipment::where('shipper_id', $shipperId)->whereIn('shipment_status', $deliveredStatuses)->count(),
            'undelivered' => Shipment::where('shipper_id', $shipperId)->whereIn('shipment_status', $undeliveredStatuses)->count(),
            'loaded' => Shipment::where('shipper_id', $shipperId)->where('shipment_status', ShipmentStatus::Loaded)->count(),
            'paid_invoices' => Invoice::whereHas('shipment', fn($q) => $q->where('shipper_id', $shipperId)->where('payment_status', PaymentStatus::Paid))->count(),
            'due_invoices' => Invoice::where('status', InvoiceStatus::Completed)
                ->whereHas('shipment', fn($q) => $q->where('shipper_id', $shipperId)->where('payment_status', '!=', PaymentStatus::Paid))
                ->count(),
            'wallet_balance' => auth()->user()->shipper?->wallet?->balance ?? 0,
            'topup_requests' => WalletTopUp::where('shipper_id', $shipperId)->count(),
            'total_roro' => Shipment::where('shipper_id', $shipperId)->where('shipping_mode', ShippingMode::Roro)->count(),
            'total_container' => Shipment::where('shipper_id', $shipperId)->where('shipping_mode', ShippingMode::Container)->count(),
        ];
    }

    #[Computed]
    public function shipments(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $shipperId = auth()->user()->shipper?->id;

        return Shipment::query()
            ->where('shipper_id', $shipperId)
            ->with(['vehicles.driver', 'invoice', 'originPort.state', 'originPort.country'])
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($searchQuery): void {
                    $term = '%' . trim($this->search) . '%';
                    $searchQuery->where('reference_no', 'like', $term)
                        ->orWhereHas('vehicles', function ($vehicleQuery) use ($term): void {
                            $vehicleQuery->where('make', 'like', $term)
                                ->orWhere('model', 'like', $term)
                                ->orWhere('year', 'like', $term)
                                ->orWhere('vin', 'like', $term);
                        });
                });
            })
            ->when($this->filterMonth !== '', function ($query): void {
                $query->whereMonth('created_at', (int) $this->filterMonth);
            })
            ->when($this->filterYear !== '', function ($query): void {
                $query->whereYear('created_at', (int) $this->filterYear);
            })
            ->latest()
            ->paginate(25);
    }

    public function years(): \Illuminate\Support\Collection
    {
        return Shipment::query()
            ->where('shipper_id', auth()->user()->shipper?->id)
            ->whereNotNull('created_at')
            ->latest('created_at')
            ->get(['created_at'])
            ->map(fn(Shipment $shipment): ?string => $shipment->created_at?->format('Y'))
            ->filter()
            ->unique()
            ->values();
    }

    #[Computed]
    public function currentMonthName(): string
    {
        if ($this->filterMonth) {
            return \Carbon\Carbon::create()->month((int) $this->filterMonth)->format('F');
        }
        return __('All Time');
    }

    #[Computed]
    public function prealerts(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Prealert::query()
            ->where('shipper_id', auth()->user()->shipper?->id)
            ->with(['vehicles', 'destinationPort'])
            ->latest()
            ->paginate(10, pageName: 'prealerts_page');
    }

    #[Computed]
    public function walletFundings(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return WalletTopUp::query()
            ->where('shipper_id', auth()->user()->shipper?->id)
            ->latest()
            ->paginate(10, pageName: 'wallet_fundings_page');
    }
}; ?>

<x-crud.page-shell>
    {{-- Summary Stats Section --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-4 mb-5">
        <flux:card as="a" href="{{ route('shipments.index') }}" wire:navigate class="flex flex-col gap-2 p-4 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <flux:icon.car-front class="size-8 text-blue-500" />
                <flux:badge color="blue" size="sm" variant="subtle">{{ __('Total') }}</flux:badge>
            </div>
            <div>
                <flux:heading level="3" size="xl" class="font-bold">{{ number_format($this->stats['total_shipments']) }}
                </flux:heading>
                <flux:subheading>{{ __('My Shipments') }}</flux:subheading>
            </div>
        </flux:card>

        <flux:card as="a" href="{{ route('prealerts.index') }}" wire:navigate class="flex flex-col gap-2 p-4 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <flux:icon.bell class="size-8 text-amber-500" />
                <flux:badge color="amber" size="sm" variant="subtle">{{ __('Alerts') }}</flux:badge>
            </div>
            <div>
                <flux:heading level="3" size="xl" class="font-bold">{{ number_format($this->stats['total_prealerts']) }}
                </flux:heading>
                <flux:subheading>{{ __('My Prealerts') }}</flux:subheading>
            </div>
        </flux:card>

        <flux:card as="a" href="{{ route('shipments.index', ['filterShipmentStatus' => 'DELIVERED']) }}" wire:navigate class="flex flex-col gap-2 p-4 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <flux:icon.check-circle class="size-8 text-emerald-500" />
                <flux:badge color="emerald" size="sm" variant="subtle">{{ __('Complete') }}</flux:badge>
            </div>
            <div>
                <flux:heading level="3" size="xl" class="font-bold text-emerald-600 dark:text-emerald-400">
                    {{ number_format($this->stats['delivered']) }}
                </flux:heading>
                <flux:subheading>{{ __('Delivered') }}</flux:subheading>
            </div>
        </flux:card>

        <flux:card as="a" href="{{ route('shipments.index', ['filterShipmentStatus' => 'PENDING']) }}" wire:navigate class="flex flex-col gap-2 p-4 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <flux:icon.clock class="size-8 text-rose-500" />
                <flux:badge color="rose" size="sm" variant="subtle">{{ __('Active') }}</flux:badge>
            </div>
            <div>
                <flux:heading level="3" size="xl" class="font-bold text-rose-600 dark:text-rose-400">
                    {{ number_format($this->stats['undelivered']) }}
                </flux:heading>
                <flux:subheading>{{ __('In Transit') }}</flux:subheading>
            </div>
        </flux:card>

        <flux:card as="a" href="{{ route('shipments.index', ['filterShipmentStatus' => 'LOADED']) }}" wire:navigate class="flex flex-col gap-2 p-4 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <flux:icon.container class="size-8 text-indigo-500" />
                <flux:badge color="indigo" size="sm" variant="subtle">{{ __('Loaded') }}</flux:badge>
            </div>
            <div>
                <flux:heading level="3" size="xl" class="font-bold">{{ number_format($this->stats['loaded']) }}
                </flux:heading>
                <flux:subheading>{{ __('Loaded') }}</flux:subheading>
            </div>
        </flux:card>

        <flux:card as="a" href="{{ route('shipments.index') }}" wire:navigate class="flex flex-col gap-2 p-4 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <flux:icon.truck class="size-8 text-slate-500" />
                <flux:badge color="slate" size="sm" variant="subtle">{{ __('Roro') }}</flux:badge>
            </div>
            <div>
                <flux:heading level="3" size="xl" class="font-bold">{{ number_format($this->stats['total_roro']) }}
                </flux:heading>
                <flux:subheading>{{ __('My Roro') }}</flux:subheading>
            </div>
        </flux:card>

        <flux:card as="a" href="{{ route('shipments.index') }}" wire:navigate class="flex flex-col gap-2 p-4 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <flux:icon.container class="size-8 text-blue-600" />
                <flux:badge color="blue" size="sm" variant="subtle">{{ __('Container') }}</flux:badge>
            </div>
            <div>
                <flux:heading level="3" size="xl" class="font-bold">{{ number_format($this->stats['total_container']) }}
                </flux:heading>
                <flux:subheading>{{ __('My Container') }}</flux:subheading>
            </div>
        </flux:card>

        <flux:card as="a" href="{{ route('shipments.index') }}" wire:navigate class="flex flex-col gap-2 p-4 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <flux:icon.banknotes class="size-8 text-teal-500" />
                <flux:badge color="teal" size="sm" variant="subtle">{{ __('Paid') }}</flux:badge>
            </div>
            <div>
                <flux:heading level="3" size="xl" class="font-bold">{{ number_format($this->stats['paid_invoices']) }}
                </flux:heading>
                <flux:subheading>{{ __('My Paid Invoices') }}</flux:subheading>
            </div>
        </flux:card>

        <flux:card as="a" href="{{ route('shipments.index') }}" wire:navigate class="flex flex-col gap-2 p-4 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <flux:icon.exclamation-circle class="size-8 text-orange-500" />
                <flux:badge color="orange" size="sm" variant="subtle">{{ __('Due') }}</flux:badge>
            </div>
            <div>
                <flux:heading level="3" size="xl" class="font-bold text-orange-600 dark:text-orange-400">
                    {{ number_format($this->stats['due_invoices']) }}
                </flux:heading>
                <flux:subheading>{{ __('My Due Invoices') }}</flux:subheading>
            </div>
        </flux:card>

        <flux:card as="a" href="{{ route('shipper.wallet.index') }}" wire:navigate class="flex flex-col gap-2 p-4 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <flux:icon.wallet class="size-8 text-purple-500" />
                <flux:badge color="purple" size="sm" variant="subtle">{{ __('Balance') }}</flux:badge>
            </div>
            <div>
                <flux:heading level="3" size="xl" class="font-bold">
                    ${{ number_format($this->stats['wallet_balance'], 2) }}</flux:heading>
                <flux:subheading>{{ __('My Wallet Balance') }}</flux:subheading>
            </div>
        </flux:card>

        <flux:card as="a" href="{{ route('shipper.wallet.index') }}" wire:navigate class="flex flex-col gap-2 p-4 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <flux:icon.arrow-up-circle class="size-8 text-cyan-500" />
                <flux:badge color="cyan" size="sm" variant="subtle">{{ __('Requests') }}</flux:badge>
            </div>
            <div>
                <flux:heading level="3" size="xl" class="font-bold">{{ number_format($this->stats['topup_requests']) }}
                </flux:heading>
                <flux:subheading>{{ __('My Top-up Requests') }}</flux:subheading>
            </div>
        </flux:card>
    </div>

    {{-- Shipments Table Section --}}
    <x-crud.panel class="p-4 sm:p-6">
        <div class="flex flex-col gap-6">
            {{-- Custom Segmented Tabs --}}
            <div class="flex p-1 bg-zinc-100 dark:bg-zinc-800 rounded-lg w-full sm:w-max overflow-x-auto no-scrollbar">
                <button type="button" wire:click="$set('activeTab', 'shipments')" @class([
                    'flex-1 sm:flex-none flex items-center justify-center gap-2 px-4 py-1.5 text-sm font-medium rounded-md transition-all whitespace-nowrap',
                    'bg-white dark:bg-zinc-700 shadow-sm text-zinc-900 dark:text-white' => $activeTab === 'shipments',
                    'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' => $activeTab !== 'shipments',
                ])>
                    <flux:icon.car-front class="size-4" />
                    <span>{{ __('Shipments') }}</span>
                    <flux:badge color="zinc" size="sm" inset="top bottom" class="ml-1">
                        {{ $this->shipments->total() }}
                    </flux:badge>
                </button>

                <button type="button" wire:click="$set('activeTab', 'prealerts')" @class([
                    'flex-1 sm:flex-none flex items-center justify-center gap-2 px-4 py-1.5 text-sm font-medium rounded-md transition-all whitespace-nowrap',
                    'bg-white dark:bg-zinc-700 shadow-sm text-zinc-900 dark:text-white' => $activeTab === 'prealerts',
                    'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' => $activeTab !== 'prealerts',
                ])>
                    <flux:icon.bell class="size-4" />
                    <span>{{ __('Prealerts') }}</span>
                    <flux:badge color="zinc" size="sm" inset="top bottom" class="ml-1">
                        {{ $this->prealerts->total() }}
                    </flux:badge>
                </button>

                <button type="button" wire:click="$set('activeTab', 'wallet')" @class([
                    'flex-1 sm:flex-none flex items-center justify-center gap-2 px-4 py-1.5 text-sm font-medium rounded-md transition-all whitespace-nowrap',
                    'bg-white dark:bg-zinc-700 shadow-sm text-zinc-900 dark:text-white' => $activeTab === 'wallet',
                    'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' => $activeTab !== 'wallet',
                ])>
                    <flux:icon.wallet class="size-4" />
                    <span>{{ __('Wallet') }}</span>
                    <flux:badge color="zinc" size="sm" inset="top bottom" class="ml-1">
                        {{ $this->walletFundings->total() }}
                    </flux:badge>
                </button>
            </div>

            @if ($activeTab === 'shipments')
                <div class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4 mb-6">
                        <flux:input wire:model.live.debounce.300ms="search" size="sm" icon="magnifying-glass"
                            placeholder="{{ __('Search...') }}" />
                        <flux:select wire:model.live="filterMonth" size="sm">
                            <flux:select.option value="">{{ __('All Months') }}</flux:select.option>
                            @foreach(range(1, 12) as $month)
                                <flux:select.option value="{{ $month }}">
                                    {{ \Carbon\Carbon::create()->month($month)->format('F') }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model.live="filterYear" size="sm">
                            @foreach($this->years() as $year)
                                <flux:select.option value="{{ $year }}">{{ $year }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <div class="col-span-2"></div>
                    </div>

                    <div class="-mx-4 sm:-mx-6 overflow-hidden border-t border-zinc-200 dark:border-zinc-800">
                        <flux:table :paginate="$this->shipments">
                            <flux:table.columns sticky class="bg-white dark:bg-zinc-900">
                                <flux:table.column>{{ __('Ref / VIN') }}</flux:table.column>
                                <flux:table.column>{{ __('Vehicle') }}</flux:table.column>
                                <flux:table.column>{{ __('Origin Port') }}</flux:table.column>
                                <flux:table.column>{{ __('Invoice') }}</flux:table.column>
                                <flux:table.column>{{ __('Status') }}</flux:table.column>
                            </flux:table.columns>

                            <flux:table.rows>
                                @foreach ($this->shipments as $shipment)
                                    <flux:table.row :key="$shipment->id">
                                        <flux:table.cell>
                                            <div class="flex flex-col">
                                                <a href="{{ route('shipments.show', $shipment) }}" wire:navigate
                                                    class="font-bold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">
                                                    {{ $shipment->reference_no }}
                                                </a>
                                                <span class="text-xs text-zinc-500 font-mono">
                                                    {{ $shipment->isContainer() ? __('Container') : ($shipment->vehicles->first()?->vin ? substr($shipment->vehicles->first()->vin, -6) : '—') }}
                                                </span>
                                            </div>
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            <div class="flex flex-col text-sm">
                                                @if($shipment->isContainer())
                                                    <span
                                                        class="font-semibold">{{ __(':count Vehicles', ['count' => $shipment->vehicles->count()]) }}</span>
                                                @else
                                                    @php $v = $shipment->vehicles->first(); @endphp
                                                    <span class="font-medium">{{ $v?->year }} {{ $v?->make }}
                                                        {{ $v?->model }}</span>
                                                @endif
                                            </div>
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            <span class="text-sm">{{ $shipment->originPort->name ?? '—' }}</span>
                                        </flux:table.cell>
                                        <flux:table.cell class="font-mono text-sm">
                                            ${{ number_format((float) ($shipment->invoice?->total_amount ?? 0), 2) }}
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            <flux:badge size="sm"
                                                :color="$shipment->shipment_status === ShipmentStatus::Completed ? 'emerald' : ($shipment->shipment_status === ShipmentStatus::Cancelled ? 'rose' : 'zinc')"
                                                variant="subtle">
                                                {{ $shipment->shipmentStatusDisplay() }}
                                            </flux:badge>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </div>
            @endif

                @if ($activeTab === 'prealerts')
                    <div class="space-y-6">
                        <div class="-mx-4 sm:-mx-6 overflow-hidden border-t border-zinc-200 dark:border-zinc-800">
                            <flux:table :paginate="$this->prealerts">
                                <flux:table.columns>
                                    <flux:table.column>{{ __('Vehicle') }}</flux:table.column>
                                    <flux:table.column>{{ __('Carrier') }}</flux:table.column>
                                    <flux:table.column>{{ __('Destination') }}</flux:table.column>
                                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                                </flux:table.columns>

                                <flux:table.rows>
                                    @foreach ($this->prealerts as $prealert)
                                        <flux:table.row :key="$prealert->id">
                                            <flux:table.cell>
                                                <div class="flex flex-col text-sm">
                                                    @php $v = $prealert->vehicles->first(); @endphp
                                                    <span class="font-medium">{{ $v?->year }} {{ $v?->make }}
                                                        {{ $v?->model }}</span>
                                                    <span class="text-xs text-zinc-500 font-mono">{{ $v?->vin }}</span>
                                                </div>
                                            </flux:table.cell>
                                            <flux:table.cell>{{ $prealert->carrier?->name ?? '—' }}</flux:table.cell>
                                            <flux:table.cell>{{ $prealert->destinationPort?->name ?? '—' }}
                                            </flux:table.cell>
                                            <flux:table.cell>
                                                <flux:badge size="sm" variant="subtle">
                                                    {{ $prealert->status->name }}
                                                </flux:badge>
                                            </flux:table.cell>
                                            <flux:table.cell class="text-xs text-zinc-500">
                                                {{ $prealert->created_at->format('M d, Y') }}
                                            </flux:table.cell>
                                        </flux:table.row>
                                    @endforeach
                                </flux:table.rows>
                            </flux:table>
                        </div>
                @endif

                    @if ($activeTab === 'wallet')
                        <div class="space-y-6">
                            <div class="-mx-4 sm:-mx-6 overflow-hidden border-t border-zinc-200 dark:border-zinc-800">
                                <flux:table :paginate="$this->walletFundings">
                                    <flux:table.columns>
                                        <flux:table.column>{{ __('Reference') }}</flux:table.column>
                                        <flux:table.column>{{ __('Amount') }}</flux:table.column>
                                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                                        <flux:table.column>{{ __('Date') }}</flux:table.column>
                                    </flux:table.columns>

                                    <flux:table.rows>
                                        @foreach ($this->walletFundings as $topup)
                                            <flux:table.row :key="$topup->id">
                                                <flux:table.cell class="font-mono text-sm">{{ $topup->reference }}
                                                </flux:table.cell>
                                                <flux:table.cell class="font-bold text-emerald-600 dark:text-emerald-400">
                                                    ${{ number_format((float) $topup->amount, 2) }}
                                                </flux:table.cell>
                                                <flux:table.cell>
                                                    <flux:badge size="sm" variant="subtle">
                                                        {{ $topup->status->name }}
                                                    </flux:badge>
                                                </flux:table.cell>
                                                <flux:table.cell class="text-xs text-zinc-500">
                                                    {{ $topup->created_at->format('M d, Y') }}
                                                </flux:table.cell>
                                            </flux:table.row>
                                        @endforeach
                                    </flux:table.rows>
                                </flux:table>
                            </div>
                    @endif
                    </div>
    </x-crud.panel>
</x-crud.page-shell>