<?php

declare(strict_types=1);

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\Staff;
use App\Models\Shipper;
use App\Concerns\HandlesShipmentPayments;
use WireUi\Traits\WireUiActions;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Shipments')] class extends Component {
    use WithPagination;
    use WireUiActions;
    use HandlesShipmentPayments;

    public bool $showMakePaymentModal = false;
    public ?Shipment $selectedShipmentForPayment = null;

    public bool $showReleaseBookingModal = false;
    public ?int $pendingReleaseShipmentId = null;

    public string $search = '';

    #[Url]
    public string $filterMonth = '';

    #[Url]
    public string $filterYear = '';

    #[Url]
    public string $filterShipper = '';

    #[Url]
    public string $filterShipmentStatus = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'filterMonth', 'filterYear', 'filterShipper', 'filterShipmentStatus'], true)) {
            $this->resetPage();
        }
    }

    public function shipments(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $user = auth()->user();

        return Shipment::query()
            ->with(['shipper.user', 'vehicles.driver', 'invoice', 'originPort.state', 'originPort.country', 'workshop', 'bookingAgent.user'])
            ->when(!($user?->hasRole('super_admin') || $user?->staff()->exists()), function ($query) use ($user): void {
                $query->where('shipper_id', $user?->shipper?->id);
            })
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($searchQuery): void {
                    $term = '%' . trim($this->search) . '%';
                    $searchQuery->where('reference_no', 'like', $term)
                        ->orWhereHas('vehicles', function ($vehicleQuery) use ($term): void {
                            $vehicleQuery->where('make', 'like', $term)
                                ->orWhere('model', 'like', $term)
                                ->orWhere('year', 'like', $term)
                                ->orWhere('vin', 'like', $term);
                        })
                        ->orWhereHas('shipper', function ($shipperQuery) use ($term): void {
                            $shipperQuery->where('company_name', 'like', $term);
                        })
                        ->orWhereHas('shipper.user', function ($userQuery) use ($term): void {
                            $userQuery->where('name', 'like', $term);
                        });
                });
            })
            ->when($this->filterMonth !== '', function ($query): void {
                $query->whereMonth('created_at', (int) $this->filterMonth);
            })
            ->when($this->filterYear !== '', function ($query): void {
                $query->whereYear('created_at', (int) $this->filterYear);
            })
            ->when($this->filterShipper !== '', function ($query): void {
                $query->where('shipper_id', (int) $this->filterShipper);
            })
            ->when($this->filterShipmentStatus !== '', function ($query): void {
                $query->where('shipment_status', $this->filterShipmentStatus);
            })
            ->latest()
            ->paginate(25);
    }

    public function shippers(): \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection
    {
        $user = auth()->user();
        if (!($user?->hasRole('super_admin') || $user?->staff()->exists())) {
            return collect();
        }

        return Shipper::query()
            ->with('user')
            ->orderBy('company_name')
            ->get();
    }

    public function years(): \Illuminate\Support\Collection
    {
        $user = auth()->user();

        return Shipment::query()
            ->when(!($user?->hasRole('super_admin') || $user?->staff()->exists()), function ($query) use ($user): void {
                $query->where('shipper_id', $user?->shipper?->id);
            })
            ->whereNotNull('created_at')
            ->latest('created_at')
            ->get(['created_at'])
            ->map(fn(Shipment $shipment): ?string => $shipment->created_at?->format('Y'))
            ->filter()
            ->unique()
            ->values();
    }

    public function openPaymentModal(int $shipmentId): void
    {
        $this->selectedShipmentForPayment = Shipment::with(['shipper.wallet', 'invoice'])->findOrFail($shipmentId);
        $this->showMakePaymentModal = true;
    }

    public function payViaWallet(): void
    {
        if ($this->selectedShipmentForPayment && $this->processShipmentPayment($this->selectedShipmentForPayment)) {
            $this->showMakePaymentModal = false;
            $this->selectedShipmentForPayment = null;
        }
    }

    public function claimBooking(int $shipmentId): void
    {
        $user = auth()->user();
        $staffId = $user?->staff?->id;

        if ($staffId === null) {
            $this->notification()->error(__('Access Denied'), __('Only staff members can claim bookings.'));

            return;
        }

        $updated = Shipment::query()
            ->where('id', $shipmentId)
            ->where('shipment_status', ShipmentStatus::Booking)
            ->whereNull('booking_agent_id')
            ->update(['booking_agent_id' => $staffId]);

        if ($updated === 0) {
            $this->notification()->error(__('Already Claimed'), __('This booking is already claimed by another agent.'));

            return;
        }

        $this->notification()->success(__('Booking Claimed'), __('You are now managing this booking.'));
    }

    public function releaseBooking(int $shipmentId): void
    {
        $user = auth()->user();
        $shipment = Shipment::query()->findOrFail($shipmentId);

        if ($shipment->booking_agent_id === null) {
            return;
        }

        $isAdmin = $user?->hasRole(['super_admin', 'staff_admin']);
        $isOwner = $user?->staff?->id === $shipment->booking_agent_id;

        if (!$isAdmin && !$isOwner) {
            $this->notification()->error(__('Access Denied'), __('You cannot release a booking claimed by another agent.'));

            return;
        }

        $shipment->update(['booking_agent_id' => null]);
        $this->showReleaseBookingModal = false;
        $this->pendingReleaseShipmentId = null;
        $this->notification()->success(__('Booking Released'), __('This booking is now unclaimed.'));
    }
}; ?>

<x-crud.page-shell>
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <div class="rounded-lg bg-zinc-100 p-2 dark:bg-zinc-800">
                <flux:icon.car-front class="size-6 text-zinc-600 dark:text-zinc-400" />
            </div>
            <x-crud.page-header :heading="__('Shipments')" :subheading="__('Manage all active shipments and their status.')" class="mb-0!" />
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4 mb-6">
        <flux:input wire:model.live.debounce.300ms="search" label="{{ __('Search') }}" icon="magnifying-glass"
            placeholder="{{ __('VIN, Ref, Vehicle, Shipper') }}" />

        <flux:select wire:model.live="filterMonth" label="{{ __('Month') }}" icon="calendar-days">
            <flux:select.option value="">{{ __('All Months') }}</flux:select.option>
            @foreach(range(1, 12) as $month)
                <flux:select.option value="{{ $month }}">
                    {{ \Carbon\Carbon::create()->month($month)->format('F') }}
                </flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filterYear" label="{{ __('Year') }}" icon="calendar">
            <flux:select.option value="">{{ __('All Years') }}</flux:select.option>
            @foreach($this->years() as $year)
                <flux:select.option value="{{ $year }}">{{ $year }}</flux:select.option>
            @endforeach
        </flux:select>

        @if (auth()->user()?->hasRole('super_admin') || auth()->user()?->hasRole('Super Manager') || auth()->user()?->staff()->exists())
            <flux:select wire:model.live="filterShipper" label="{{ __('Shipper') }}" icon="user-group">
                <flux:select.option value="">{{ __('All Shippers') }}</flux:select.option>
                @foreach($this->shippers() as $shipper)
                    <flux:select.option value="{{ $shipper->id }}">
                        {{ $shipper->user?->name ?: $shipper->company_name }}
                    </flux:select.option>
                @endforeach
            </flux:select>
        @endif

        <flux:select wire:model.live="filterShipmentStatus" label="{{ __('Shipment Status') }}" icon="car-front">
            <flux:select.option value="">{{ __('All Statuses') }}</flux:select.option>
            @foreach(ShipmentStatus::cases() as $status)
                <flux:select.option value="{{ $status->value }}">{{ $status->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <x-crud.panel class="p-6">

        <flux:table :paginate="$this->shipments()">
            <flux:table.columns sticky class="bg-white dark:bg-zinc-900">
                <flux:table.column>{{ __('Ref / VIN / Created') }}</flux:table.column>
                <flux:table.column>{{ __('Vehicle') }}</flux:table.column>
                <flux:table.column>{{ __('Shipper') }}</flux:table.column>
                <flux:table.column>{{ __('Origin Port') }}</flux:table.column>
                <flux:table.column>{{ __('Invoice Total') }}</flux:table.column>
                <flux:table.column>{{ __('Payment Status') }}</flux:table.column>
                <flux:table.column>{{ __('Shipment Status') }}</flux:table.column>
                <flux:table.column>{{ __('Invoice Status') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->shipments() as $shipment)
                    <flux:table.row :key="$shipment->id">
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <a href="{{ route('shipments.show', $shipment) }}" wire:navigate
                                    class="font-bold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">
                                    {{ $shipment->reference_no }}
                                </a>
                                <span class="text-xs text-zinc-500 font-mono">
                                    @if($shipment->isContainer())
                                        {{ __('Container') }}
                                    @else
                                        {{ $shipment->vehicles->first()?->vin ? substr($shipment->vehicles->first()->vin, -6) : '—' }}
                                    @endif
                                </span>
                                <span class="text-xs text-zinc-500">
                                    {{ $shipment->created_at?->format('d-m-y') ?? '—' }}
                                </span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-col">
                                @if($shipment->isContainer())
                                    <span class="font-bold text-indigo-600">
                                        {{ __(':count Vehicles', ['count' => $shipment->vehicles->count()]) }}
                                    </span>
                                    <flux:badge size="xs" variant="outline">{{ __('Container') }}</flux:badge>
                                @else
                                    @php $v = $shipment->vehicles->first(); @endphp
                                    <span class="font-semibold">
                                        {{ trim($v?->year ?? '') ?: '—' }}
                                    </span>
                                    <span class="font-semibold">
                                        {{ trim($v?->make ?? '') ?: '—' }}
                                    </span>
                                    <span class="font-semibold">
                                        {{ trim($v?->model ?? '') ?: '—' }}
                                    </span>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $shipment->shipper?->user?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if($shipment->originPort)
                                {{ $shipment->originPort->name }}
                                <span class="text-xs text-zinc-500">
                                    ({{ $shipment->originPort->state?->code ?? '—' }} -
                                    {{ $shipment->originPort->country?->iso2 ?? '—' }})
                                </span>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="font-mono">
                            @if(auth()->user()->hasRole('shipper') && $shipment->invoice_status !== \App\Enums\InvoiceStatus::Completed)
                                <span class="text-zinc-400"
                                    title="{{ __('Invoice total available when completed') }}">$0.00</span>
                            @else
                                ${{ number_format((float) ($shipment->invoice?->total_amount ?? 0), 2) }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($shipment->payment_status === \App\Enums\PaymentStatus::AwaitingPayment && (auth()->user()->can('shipments.pay') || auth()->user()->hasRole('super_admin')))
                                <flux:button size="sm" variant="ghost" icon="wallet"
                                    wire:click="openPaymentModal({{ $shipment->id }})">
                                    {{ __('Pay Now') }}
                                </flux:button>
                            @else
                                <flux:badge size="sm" color="emerald" variant="subtle">
                                    {{ $shipment->payment_status?->name ?? '—' }}
                                </flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <flux:badge size="sm" color="zinc" variant="subtle">
                                    {{ $shipment->shipmentStatusDisplay() }}
                                </flux:badge>
                                @if($shipment->booked_without_title)
                                    <flux:badge size="sm" color="rose" variant="subtle" icon="document-minus">
                                        {{ __('No Title') }}
                                    </flux:badge>
                                @endif
                                @if($shipment->shipment_status === \App\Enums\ShipmentStatus::Booking && (auth()->user()?->hasRole('super_admin') || auth()->user()?->hasRole('Super Manager') || auth()->user()?->staff()?->exists()))
                                    @if($shipment->booking_agent_id === null)
                                        <flux:button size="xs" variant="subtle" icon="hand-raised"
                                            wire:click="claimBooking({{ $shipment->id }})">
                                            {{ __('Claim') }}
                                        </flux:button>
                                    @elseif($shipment->booking_agent_id === auth()->user()?->staff?->id)
                                        <flux:badge size="sm" color="sky" variant="subtle" icon="check-circle">
                                            {{ __('You') }}
                                        </flux:badge>
                                        <flux:button size="xs" variant="ghost" icon="x-mark"
                                            wire:click="$wire.pendingReleaseShipmentId = {{ $shipment->id }}; $wire.showReleaseBookingModal = true" />
                                    @else
                                        <flux:badge size="sm" color="rose" variant="subtle" icon="lock-closed">
                                            {{ $shipment->bookingAgent?->user?->name ?? __('Agent') }}
                                        </flux:badge>
                                    @endif
                                @endif
                            </div>
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

    @if($selectedShipmentForPayment)
        {{-- Make Payment Modal --}}
        <flux:modal name="make-payment" wire:model="showMakePaymentModal" variant="filled" class="md:w-[500px]">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Complete Payment') }}</flux:heading>
                    <flux:subheading>
                        {{ __('Pay for shipment :ref using wallet balance.', ['ref' => $selectedShipmentForPayment->reference_no]) }}
                    </flux:subheading>
                </div>

                <div
                    class="p-4 bg-zinc-50 dark:bg-zinc-800/60 rounded-xl border border-zinc-200 dark:border-zinc-700 space-y-3">
                    <div class="flex justify-between items-center">
                        <flux:text>{{ __('Invoice Amount') }}</flux:text>
                        <flux:text class="font-mono font-bold text-indigo-600 dark:text-indigo-400">
                            {{ '$' . number_format((float) ($selectedShipmentForPayment->invoice?->total_amount ?? 0), 2) }}
                        </flux:text>
                    </div>
                    <div class="flex justify-between items-center">
                        <flux:text>{{ __('Your Wallet Balance') }}</flux:text>
                        <flux:text class="font-mono font-semibold">
                            {{ '$' . number_format((float) ($selectedShipmentForPayment->shipper?->wallet?->balance ?? 0), 2) }}
                        </flux:text>
                    </div>
                </div>

                @php
                    $balance = (float) ($selectedShipmentForPayment->shipper?->wallet?->balance ?? 0);
                    $total = (float) ($selectedShipmentForPayment->invoice?->total_amount ?? 0);
                    $canPay = $balance >= $total;
                @endphp

                @if(!$canPay)
                    <flux:callout variant="danger" icon="exclamation-circle">
                        {{ __('Your balance is insufficient. Please top up your wallet to proceed.') }}
                    </flux:callout>
                @else
                    <flux:callout variant="info" icon="information-circle">
                        {{ __('Funds will be deducted immediately from your wallet.') }}
                    </flux:callout>
                @endif

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:button variant="ghost" wire:click="$set('showMakePaymentModal', false)">{{ __('Cancel') }}
                    </flux:button>
                    <flux:button variant="primary" icon="check" wire:click="payViaWallet" :disabled="!$canPay">
                        {{ __('Confirm & Pay') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
    {{-- Release Booking Confirm Modal --}}
    <flux:modal name="release-booking" wire:model="showReleaseBookingModal" variant="filled" class="md:w-[400px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Release Booking') }}</flux:heading>
                <flux:subheading>
                    {{ __('Are you sure you want to release this booking? It will become available for other agents to claim.') }}
                </flux:subheading>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button variant="ghost" wire:click="$set('showReleaseBookingModal', false)">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="danger" icon="arrow-path"
                    wire:click="releaseBooking({{ $pendingReleaseShipmentId }})">
                    {{ __('Release') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</x-crud.page-shell>