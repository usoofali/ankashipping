<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\Shipper;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use WireUi\Traits\WireUiActions;

new #[Title('Shipper')] class extends Component {
    use WireUiActions;
    use WithPagination;

    public Shipper $shipper;

    public string $search = '';

    public string $filterMonth = '';

    public string $filterYear = '';

    public string $filterShipmentStatus = '';

    public string $filterPaymentStatus = '';

    public string $filterInvoiceStatus = '';

    public bool $showDiscountModal = false;

    public string $discount_amount = '0.00';

    public function mount(Request $request, Shipper $shipper): void
    {
        $this->authorize('view', $shipper);

        if ($request->filled('notification')) {
            $request->user()
                ?->notifications()
                ->whereKey($request->query('notification'))
                ->first()
                ?->markAsRead();
        }

        $this->shipper = $shipper->load(['user', 'country', 'state', 'city', 'wallet']);
        $this->shipper->loadCount(['consignees', 'prealerts']);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'filterMonth', 'filterYear', 'filterShipmentStatus', 'filterPaymentStatus', 'filterInvoiceStatus'], true)) {
            $this->resetPage();
        }
    }

    public function updatedShowDiscountModal(bool $value): void
    {
        if (! $value) {
            return;
        }

        $this->discount_amount = number_format((float) $this->shipper->discount_amount, 2, '.', '');
    }

    public function openDiscountModal(): void
    {
        $this->authorize('update', $this->shipper);
        $this->discount_amount = number_format((float) $this->shipper->discount_amount, 2, '.', '');
        $this->showDiscountModal = true;
    }

    public function saveDiscount(): void
    {
        $this->authorize('update', $this->shipper);

        $validator = Validator::make(
            ['discount_amount' => $this->discount_amount],
            [
                'discount_amount' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            ],
        );

        $validated = $validator->validate();

        $this->shipper->update([
            'discount_amount' => $validated['discount_amount'],
        ]);

        $this->shipper->refresh();
        $this->shipper->load(['user', 'country', 'state', 'city', 'wallet']);

        $this->showDiscountModal = false;

        $this->notification()->success(__('Shipper discount updated.'));
    }

    public function shipments(): LengthAwarePaginator
    {
        return Shipment::query()
            ->where('shipper_id', $this->shipper->id)
            ->with(['vehicle', 'invoice.payment', 'originPort.state', 'originPort.country', 'driver', 'workshop'])
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($searchQuery): void {
                    $term = '%'.trim($this->search).'%';
                    $searchQuery->where('vin', 'like', $term)
                        ->orWhere('reference_no', 'like', $term)
                        ->orWhereHas('vehicle', function ($vehicleQuery) use ($term): void {
                            $vehicleQuery->where('make', 'like', $term)
                                ->orWhere('model', 'like', $term)
                                ->orWhere('year', 'like', $term);
                        });
                });
            })
            ->when($this->filterMonth !== '', function ($query): void {
                $query->whereMonth('created_at', (int) $this->filterMonth);
            })
            ->when($this->filterYear !== '', function ($query): void {
                $query->whereYear('created_at', (int) $this->filterYear);
            })
            ->when($this->filterShipmentStatus !== '', function ($query): void {
                $query->where('shipment_status', $this->filterShipmentStatus);
            })
            ->when($this->filterPaymentStatus !== '', function ($query): void {
                $query->where('payment_status', $this->filterPaymentStatus);
            })
            ->when($this->filterInvoiceStatus !== '', function ($query): void {
                $query->where('invoice_status', $this->filterInvoiceStatus);
            })
            ->latest()
            ->paginate(15);
    }

    public function years(): Collection
    {
        return Shipment::query()
            ->where('shipper_id', $this->shipper->id)
            ->whereNotNull('created_at')
            ->latest('created_at')
            ->get(['created_at'])
            ->map(fn (Shipment $shipment): ?string => $shipment->created_at?->format('Y'))
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Paid: invoices for this shipper where payment exists and status is Paid — sum payment amounts and count those invoices.
     * Outstanding: invoices for this shipper without a Paid payment — sum invoice total_amount (one payment per invoice).
     */
    #[Computed]
    public function invoicePaymentSummary(): array
    {
        $shipperId = $this->shipper->id;

        $paidCount = Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereHas('invoice', fn ($q) => $q->whereHas('shipment', fn ($sq) => $sq->where('shipper_id', $shipperId)))
            ->count();

        $paidSum = (float) Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereHas('invoice', fn ($q) => $q->whereHas('shipment', fn ($sq) => $sq->where('shipper_id', $shipperId)))
            ->sum('amount');

        $outstandingQuery = Invoice::query()
            ->whereHas('shipment', fn ($q) => $q->where('shipper_id', $shipperId))
            ->where(function ($query): void {
                $query->whereDoesntHave('payment')
                    ->orWhereHas('payment', fn ($q) => $q->where('status', '!=', PaymentStatus::Paid));
            });

        $outstandingCount = (clone $outstandingQuery)->count();
        $outstandingSum = (float) (clone $outstandingQuery)->sum('total_amount');

        return [
            'paid_count' => $paidCount,
            'paid_sum' => $paidSum,
            'outstanding_count' => $outstandingCount,
            'outstanding_sum' => $outstandingSum,
        ];
    }

    #[Computed]
    public function totalShipmentsCount(): int
    {
        return Shipment::query()->where('shipper_id', $this->shipper->id)->count();
    }

    /**
     * @return list<array{label: string, ym: string, count: int}>
     */
    #[Computed]
    public function monthlyShipmentChart(): array
    {
        $start = Carbon::now()->subMonths(11)->startOfMonth();
        $end = Carbon::now()->endOfMonth();

        $byMonth = Shipment::query()
            ->where('shipper_id', $this->shipper->id)
            ->whereBetween('created_at', [$start, $end])
            ->get(['created_at'])
            ->groupBy(fn (Shipment $s): string => $s->created_at?->format('Y-m') ?? '')
            ->map(fn (Collection $group): int => $group->count());

        $months = [];
        $max = 0;
        $cursor = $start->copy();

        for ($i = 0; $i < 12; $i++) {
            $ym = $cursor->format('Y-m');
            $count = (int) ($byMonth[$ym] ?? 0);
            $max = max($max, $count);
            $months[] = [
                'label' => $cursor->format('M Y'),
                'ym' => $ym,
                'count' => $count,
            ];
            $cursor->addMonth();
        }

        return [
            'max' => $max,
            'months' => $months,
        ];
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function shipmentStatusBreakdown(): array
    {
        $rows = Shipment::query()
            ->where('shipper_id', $this->shipper->id)
            ->selectRaw('shipment_status as status, COUNT(*) as c')
            ->groupBy('shipment_status')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $key = (string) $row->status;
            $enum = ShipmentStatus::tryFrom($key);
            $label = $enum !== null ? $enum->name : $key;
            $out[$label] = (int) $row->c;
        }

        return $out;
    }

    #[Computed]
    public function lastShipmentCreatedAt(): ?Carbon
    {
        $latest = Shipment::query()
            ->where('shipper_id', $this->shipper->id)
            ->max('created_at');

        return $latest !== null ? Carbon::parse($latest) : null;
    }
}; ?>

@php
    $currency = (string) config('financial.currency', 'USD');
    $chart = $this->monthlyShipmentChart;
    $chartMax = max(1, $chart['max']);
    $summary = $this->invoicePaymentSummary;
@endphp

<x-crud.page-shell>
    <x-crud.page-header :heading="$shipper->company_name" :subheading="__('Shipper overview and activity')">
        <x-slot name="actions">
            <div class="flex flex-wrap items-center gap-2">
                @can('update', $shipper)
                    <flux:button variant="ghost" icon="tag" wire:click="openDiscountModal">
                        {{ __('Edit discount') }}
                    </flux:button>
                    <flux:button variant="primary" :href="route('shippers.index', ['edit' => $shipper->id])" wire:navigate icon="pencil-square">
                        {{ __('Edit profile') }}
                    </flux:button>
                @else
                    <flux:button variant="primary" :href="route('shippers.index')" wire:navigate icon="arrow-left">
                        {{ __('Back to shippers') }}
                    </flux:button>
                @endcan
            </div>
        </x-slot>
    </x-crud.page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 mb-6">
        <flux:card class="border-zinc-100 dark:border-zinc-800">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Total shipments') }}</flux:text>
                    <flux:heading size="xl" class="mt-1 tabular-nums">{{ number_format($this->totalShipmentsCount) }}</flux:heading>
                </div>
                <div class="rounded-lg bg-indigo-50 p-2 dark:bg-indigo-950/40">
                    <flux:icon.cube class="size-6 text-indigo-600 dark:text-indigo-400" />
                </div>
            </div>
        </flux:card>

        <flux:card class="border-zinc-100 dark:border-zinc-800">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Payments received') }}</flux:text>
                    <flux:heading size="xl" class="mt-1 tabular-nums">${{ number_format($summary['paid_sum'], 2) }}</flux:heading>
                    <flux:text class="mt-1 text-sm text-zinc-500">{{ __(':count paid invoice(s)', ['count' => $summary['paid_count']]) }}</flux:text>
                </div>
                <div class="rounded-lg bg-emerald-50 p-2 dark:bg-emerald-950/40">
                    <flux:icon.banknotes class="size-6 text-emerald-600 dark:text-emerald-400" />
                </div>
            </div>
        </flux:card>

        <flux:card class="border-zinc-100 dark:border-zinc-800">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Outstanding invoices') }}</flux:text>
                    <flux:heading size="xl" class="mt-1 tabular-nums">${{ number_format($summary['outstanding_sum'], 2) }}</flux:heading>
                    <flux:text class="mt-1 text-sm text-zinc-500">{{ __(':count open invoice(s)', ['count' => $summary['outstanding_count']]) }}</flux:text>
                </div>
                <div class="rounded-lg bg-amber-50 p-2 dark:bg-amber-950/40">
                    <flux:icon.clock class="size-6 text-amber-600 dark:text-amber-400" />
                </div>
            </div>
        </flux:card>

        <flux:card class="border-zinc-100 dark:border-zinc-800">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Wallet balance') }}</flux:text>
                    <flux:heading size="xl" class="mt-1 tabular-nums">${{ number_format((float) ($shipper->wallet?->balance ?? 0), 2) }}</flux:heading>
                    <flux:text class="mt-1 text-sm text-zinc-500">{{ $currency }}</flux:text>
                </div>
                <div class="rounded-lg bg-sky-50 p-2 dark:bg-sky-950/40">
                    <flux:icon.wallet class="size-6 text-sky-600 dark:text-sky-400" />
                </div>
            </div>
        </flux:card>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 mb-6">
        <flux:card class="border-zinc-100 dark:border-zinc-800 lg:col-span-2">
            <flux:heading size="sm" weight="semibold" class="mb-4">{{ __('Shipments per month') }}</flux:heading>
            @if ($chart['max'] === 0)
                <flux:text class="text-sm text-zinc-500">{{ __('No shipments in the last 12 months.') }}</flux:text>
            @else
                @php
                    $barMaxPx = 160;
                @endphp
                <div class="flex min-h-52 items-end gap-1 sm:gap-2">
                    @foreach ($chart['months'] as $bar)
                        @php
                            $barPx = $chartMax > 0 ? (int) round(($bar['count'] / $chartMax) * $barMaxPx) : 0;
                            if ($bar['count'] > 0) {
                                $barPx = max(6, $barPx);
                            }
                        @endphp
                        <div class="flex min-w-0 flex-1 flex-col items-center gap-2">
                            <flux:text class="text-xs tabular-nums text-zinc-500">{{ $bar['count'] }}</flux:text>
                            <div
                                class="w-full max-w-[2.5rem] rounded-t-md bg-indigo-500/90 transition-all dark:bg-indigo-400/80"
                                style="height: {{ $barPx }}px;"
                                title="{{ $bar['label'] }}: {{ $bar['count'] }}"
                            ></div>
                            <flux:text class="max-w-full truncate text-center text-[10px] uppercase text-zinc-400 sm:text-xs">{{ $bar['label'] }}</flux:text>
                        </div>
                    @endforeach
                </div>
            @endif
        </flux:card>

        <flux:card class="space-y-4 border-zinc-100 dark:border-zinc-800">
            <flux:heading size="sm" weight="semibold">{{ __('Insights') }}</flux:heading>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between gap-2">
                    <dt class="text-zinc-500">{{ __('Consignees') }}</dt>
                    <dd class="font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format((int) $shipper->consignees_count) }}</dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-zinc-500">{{ __('Prealerts') }}</dt>
                    <dd class="font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format((int) $shipper->prealerts_count) }}</dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-zinc-500">{{ __('Per-line discount (USD)') }}</dt>
                    <dd class="font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">${{ number_format((float) $shipper->discount_amount, 2) }}</dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-zinc-500">{{ __('Last shipment') }}</dt>
                    <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $this->lastShipmentCreatedAt?->format('d M Y') ?? '—' }}</dd>
                </div>
            </dl>
            @if (count($this->shipmentStatusBreakdown) > 0)
                <div>
                    <flux:text class="mb-2 text-xs font-medium uppercase text-zinc-500">{{ __('By shipment status') }}</flux:text>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($this->shipmentStatusBreakdown as $statusLabel => $count)
                            <flux:badge size="sm" color="zinc" variant="subtle">{{ $statusLabel }}: {{ $count }}</flux:badge>
                        @endforeach
                    </div>
                </div>
            @endif
        </flux:card>
    </div>

    <flux:card class="mb-6 border-zinc-100 dark:border-zinc-800">
        <flux:heading size="sm" weight="semibold" class="mb-4">{{ __('Company profile') }}</flux:heading>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <flux:text class="text-xs font-medium uppercase text-zinc-500">{{ __('Owner') }}</flux:text>
                <flux:text>{{ $shipper->user?->name }} ({{ $shipper->user?->email }})</flux:text>
            </div>
            <div>
                <flux:text class="text-xs font-medium uppercase text-zinc-500">{{ __('Phone') }}</flux:text>
                <flux:text>{{ $shipper->phone ?: '—' }}</flux:text>
            </div>
            <div class="sm:col-span-2 lg:col-span-1">
                <flux:text class="text-xs font-medium uppercase text-zinc-500">{{ __('Address') }}</flux:text>
                <flux:text>{{ $shipper->address ?: '—' }}</flux:text>
            </div>
            <div>
                <flux:text class="text-xs font-medium uppercase text-zinc-500">{{ __('Country') }}</flux:text>
                <flux:text>{{ $shipper->country?->name ?? '—' }}</flux:text>
            </div>
            <div>
                <flux:text class="text-xs font-medium uppercase text-zinc-500">{{ __('State / region') }}</flux:text>
                <flux:text>{{ $shipper->state?->name ?? '—' }}</flux:text>
            </div>
            <div>
                <flux:text class="text-xs font-medium uppercase text-zinc-500">{{ __('City') }}</flux:text>
                <flux:text>{{ $shipper->city?->name ?? '—' }}</flux:text>
            </div>
        </div>
    </flux:card>

    <flux:heading size="lg" weight="semibold" class="mb-4">{{ __('Shipments') }}</flux:heading>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6 mb-6">
        <flux:input
            wire:model.live.debounce.300ms="search"
            label="{{ __('Search') }}"
            icon="magnifying-glass"
            placeholder="{{ __('VIN, ref, vehicle') }}"
        />

        <flux:select wire:model.live="filterMonth" label="{{ __('Month') }}" icon="calendar-days">
            <flux:select.option value="">{{ __('All months') }}</flux:select.option>
            @foreach (range(1, 12) as $month)
                <flux:select.option value="{{ $month }}">
                    {{ \Carbon\Carbon::create()->month((int) $month)->format('F') }}
                </flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filterYear" label="{{ __('Year') }}" icon="calendar">
            <flux:select.option value="">{{ __('All years') }}</flux:select.option>
            @foreach ($this->years() as $year)
                <flux:select.option value="{{ $year }}">{{ $year }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filterShipmentStatus" label="{{ __('Shipment status') }}" icon="truck">
            <flux:select.option value="">{{ __('All') }}</flux:select.option>
            @foreach (ShipmentStatus::cases() as $status)
                <flux:select.option value="{{ $status->value }}">{{ $status->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filterPaymentStatus" label="{{ __('Payment status') }}" icon="banknotes">
            <flux:select.option value="">{{ __('All') }}</flux:select.option>
            @foreach (PaymentStatus::cases() as $status)
                <flux:select.option value="{{ $status->value }}">{{ $status->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filterInvoiceStatus" label="{{ __('Invoice status') }}" icon="document-text">
            <flux:select.option value="">{{ __('All') }}</flux:select.option>
            @foreach (InvoiceStatus::cases() as $status)
                <flux:select.option value="{{ $status->value }}">{{ $status->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <x-crud.panel class="p-6">
        <flux:table :paginate="$this->shipments()">
            <flux:table.columns>
                <flux:table.column>{{ __('Ref / VIN / Created') }}</flux:table.column>
                <flux:table.column>{{ __('Vehicle') }}</flux:table.column>
                <flux:table.column>{{ __('Origin port') }}</flux:table.column>
                <flux:table.column>{{ __('Driver') }}</flux:table.column>
                <flux:table.column>{{ __('Invoice total') }}</flux:table.column>
                <flux:table.column>{{ __('Payment status') }}</flux:table.column>
                <flux:table.column>{{ __('Shipment status') }}</flux:table.column>
                <flux:table.column>{{ __('Invoice status') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->shipments() as $shipment)
                    <flux:table.row :key="$shipment->id">
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <a
                                    href="{{ route('shipments.show', $shipment) }}"
                                    wire:navigate
                                    class="font-bold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400"
                                >
                                    {{ $shipment->reference_no }}
                                </a>
                                <span class="font-mono text-xs text-zinc-500">
                                    {{ $shipment->vin ? substr($shipment->vin, -6) : '—' }}
                                </span>
                                <span class="text-xs text-zinc-500">
                                    {{ $shipment->created_at?->format('d-m-y') ?? '—' }}
                                </span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span class="font-semibold">{{ trim($shipment->vehicle?->year) ?: '—' }}</span>
                                <span class="font-semibold">{{ trim($shipment->vehicle?->make) ?: '—' }}</span>
                                <span class="font-semibold">{{ trim($shipment->vehicle?->model) ?: '—' }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($shipment->originPort)
                                {{ $shipment->originPort->name }}
                                <span class="text-xs text-zinc-500">
                                    ({{ $shipment->originPort->state?->code ?? '—' }} - {{ $shipment->originPort->country?->iso2 ?? '—' }})
                                </span>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($shipment->driver_id && $shipment->driver)
                                <div class="flex flex-col">
                                    <span class="font-semibold">{{ $shipment->driver->company ?: '—' }}</span>
                                    <span class="text-xs text-zinc-500">{{ $shipment->driver->phone ?: '—' }}</span>
                                </div>
                            @else
                                <span class="text-zinc-500">{{ __('Driver not assigned') }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="font-mono">
                            ${{ number_format((float) ($shipment->invoice?->total_amount ?? 0), 2) }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="emerald" variant="subtle">
                                {{ $shipment->payment_status?->name ?? '—' }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc" variant="subtle">
                                {{ $shipment->shipmentStatusDisplay() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="amber" variant="subtle">
                                {{ $shipment->invoice_status?->name ?? '—' }}
                            </flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </x-crud.panel>

    @can('update', $shipper)
        <flux:modal wire:model.self="showDiscountModal" class="max-w-md">
            <form wire:submit="saveDiscount" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Per-line shipper discount') }}</flux:heading>
                    <flux:text class="mt-1 text-sm text-zinc-500">
                        {{ __('Applied only to charge items marked to apply shipper discount. Currency: USD.') }}
                    </flux:text>
                </div>
                <flux:input
                    wire:model="discount_amount"
                    :label="__('Amount (USD)')"
                    type="number"
                    min="0"
                    step="0.01"
                    icon="tag"
                    required
                />
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan
</x-crud.page-shell>
