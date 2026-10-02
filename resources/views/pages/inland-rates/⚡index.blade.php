<?php

declare(strict_types=1);

use App\Models\InlandRouteRate;
use App\Models\Port;
use App\Services\InlandRateService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use WireUi\Traits\WireUiActions;

new #[Title('Inland Rates & Trends')]
    class extends Component {
    use WireUiActions;
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'port')]
    public ?int $filterPortId = null;

    public string $estimatorLocation = '';

    public ?int $estimatorPortId = null;

    public bool $isSyncing = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterPortId(): void
    {
        $this->resetPage();
    }

    public function selectRoute(string $location, int $portId): void
    {
        $this->estimatorLocation = $location;
        $this->estimatorPortId = $portId;
    }

    public function clearEstimator(): void
    {
        $this->estimatorLocation = '';
        $this->estimatorPortId = null;
    }

    public function syncRates(InlandRateService $service): void
    {
        if (!$this->isStaff) {
            $this->notification()->error(__('Unauthorized. Only staff members can trigger rate synchronization.'));

            return;
        }

        $this->isSyncing = true;
        try {
            $count = $service->syncFromHistoricalInvoices();
            $this->notification()->success(__('Successfully synchronized :count inland route rates.', ['count' => $count]));
        } catch (\Throwable $e) {
            $this->notification()->error(__('Sync failed: :error', ['error' => $e->getMessage()]));
        } finally {
            $this->isSyncing = false;
        }
    }

    #[Computed]
    public function isStaff(): bool
    {
        $user = auth()->user();

        return (bool) (
            $user?->hasRole('super_admin')
            || $user?->staff()->exists()
            || $user?->hasAnyRole(['staff_admin', 'staff_operator', 'Super Manager'])
        );
    }

    /**
     * @return array{
     *     totalRoutes: int,
     *     avgNetworkRate: float,
     *     minNetworkRate: float,
     *     maxNetworkRate: float,
     *     totalShipments: int
     * }
     */
    #[Computed]
    public function summaryStats(): array
    {
        return [
            'totalRoutes' => InlandRouteRate::count(),
            'avgNetworkRate' => round((float) (InlandRouteRate::avg('average_rate') ?? 0), 2),
            'minNetworkRate' => (float) (InlandRouteRate::min('min_rate') ?? 0),
            'maxNetworkRate' => (float) (InlandRouteRate::max('max_rate') ?? 0),
            'totalShipments' => (int) InlandRouteRate::sum('shipment_count'),
        ];
    }

    /**
     * @return Collection<int, string>
     */
    #[Computed]
    public function pickupLocations(): Collection
    {
        return InlandRouteRate::query()
            ->select('pickup_location')
            ->distinct()
            ->orderBy('pickup_location')
            ->pluck('pickup_location');
    }

    /**
     * @return Collection<int, Port>
     */
    #[Computed]
    public function availablePorts(): Collection
    {
        return Port::query()
            ->whereHas('inlandRouteRates')
            ->orWhere('type', 'origin')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function estimatedRate(): ?InlandRouteRate
    {
        if ($this->estimatorLocation === '' || !$this->estimatorPortId) {
            return null;
        }

        return InlandRouteRate::query()
            ->with('originPort')
            ->where('pickup_location', strtolower(trim($this->estimatorLocation)))
            ->where('origin_port_id', $this->estimatorPortId)
            ->first();
    }

    /**
     * Historical rate timeline and SVG line chart coordinates for the selected route.
     *
     * @return array{
     *     count: int,
     *     min: float,
     *     max: float,
     *     avg: float,
     *     avgY: float,
     *     width: int,
     *     height: int,
     *     polyline: string,
     *     areaPath: string,
     *     coordinates: array<int, array{x: float, y: float, amount: float, date: string, formatted_date: string, count: int}>,
     *     startDate: string,
     *     endDate: string
     * }|null
     */
    #[Computed]
    public function chartData(): ?array
    {
        if ($this->estimatorLocation === '' || !$this->estimatorPortId) {
            return null;
        }

        /** @var InlandRateService $service */
        $service = app(InlandRateService::class);
        $points = $service->getRouteHistoryPoints($this->estimatorLocation, $this->estimatorPortId);

        if ($points->isEmpty()) {
            return null;
        }

        $amounts = $points->pluck('amount')->map(fn($val) => (float) $val)->values()->all();
        $min = min($amounts);
        $max = max($amounts);
        $avg = round(array_sum($amounts) / count($amounts), 2);
        $range = max(1.0, $max - $min);

        $width = 540;
        $height = 150;
        $padX = 35;
        $padY = 25;
        $usableWidth = $width - (2 * $padX);
        $usableHeight = $height - (2 * $padY);

        $count = count($points);
        $coordinates = [];

        foreach ($points as $index => $point) {
            $x = $count > 1
                ? $padX + ($index / ($count - 1)) * $usableWidth
                : $width / 2;

            $y = $range > 0 && $max > $min
                ? ($height - $padY) - (($point->amount - $min) / $range) * $usableHeight
                : $height / 2;

            $coordinates[] = [
                'x' => round($x, 1),
                'y' => round($y, 1),
                'amount' => $point->amount,
                'date' => $point->date,
                'formatted_date' => Carbon::parse($point->date)->format('M d, Y'),
                'count' => $point->count ?? 1,
            ];
        }

        $polyline = implode(' ', array_map(fn($c) => "{$c['x']},{$c['y']}", $coordinates));

        // Area path under line
        $firstX = $coordinates[0]['x'];
        $lastX = $coordinates[$count - 1]['x'];
        $baseY = $height - $padY + 12;
        $areaPath = "M {$firstX},{$baseY} " . implode(' ', array_map(fn($c) => "L {$c['x']},{$c['y']}", $coordinates)) . " L {$lastX},{$baseY} Z";

        // Average line Y coordinate
        $avgY = $range > 0 && $max > $min
            ? ($height - $padY) - (($avg - $min) / $range) * $usableHeight
            : $height / 2;

        return [
            'count' => $count,
            'min' => $min,
            'max' => $max,
            'avg' => $avg,
            'avgY' => round($avgY, 1),
            'width' => $width,
            'height' => $height,
            'polyline' => $polyline,
            'areaPath' => $areaPath,
            'coordinates' => $coordinates,
            'startDate' => Carbon::parse($points->first()->date)->format('M d, Y'),
            'endDate' => Carbon::parse($points->last()->date)->format('M d, Y'),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, InlandRouteRate>
     */
    #[Computed]
    public function rates(): LengthAwarePaginator
    {
        return InlandRouteRate::query()
            ->with(['originPort.state', 'originPort.country'])
            ->when($this->search !== '', function ($q): void {
                $term = strtolower(trim($this->search));
                $q->where(function ($sub) use ($term): void {
                    $sub->where('pickup_location', 'like', "%{$term}%")
                        ->orWhereHas('originPort', fn($portQuery) => $portQuery->where('name', 'like', "%{$term}%"));
                });
            })
            ->when($this->filterPortId, fn($q) => $q->where('origin_port_id', $this->filterPortId))
            ->orderByDesc('shipment_count')
            ->orderBy('pickup_location')
            ->paginate(15);
    }
}; ?>

<div>
    <x-crud.page-shell>
        {{-- Header & Staff Actions --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-4">
            <div class="flex items-center gap-3">
                <div class="rounded-xl bg-amber-500/10 p-2.5 text-amber-600 dark:text-amber-400">
                    <flux:icon.truck class="size-6" />
                </div>
                <div>
                    @if($this->isStaff)
                        <flux:heading size="xl" level="1">{{ __('Inland Towing Rates & Trends') }}</flux:heading>
                        <flux:subheading>
                            {{ __('Historical towing benchmarks, price movements, and operational metrics across US export ports.') }}
                        </flux:subheading>
                    @else
                        <flux:heading size="xl" level="1">{{ __('Inland Towing Rates') }}</flux:heading>
                        <flux:subheading>
                            {{ __('Check estimated inland towing costs and recent price trends from auction pickup yards to US export ports.') }}
                        </flux:subheading>
                    @endif
                </div>
            </div>

            @if($this->isStaff)
                <div class="flex items-center gap-2">
                    <flux:button variant="primary" icon="arrow-path" wire:click="syncRates" wire:loading.attr="disabled"
                        class="bg-amber-600 hover:bg-amber-700 text-white">
                        <span wire:loading.remove wire:target="syncRates">{{ __('Sync Rates from Invoices') }}</span>
                        <span wire:loading wire:target="syncRates">{{ __('Syncing...') }}</span>
                    </flux:button>
                </div>
            @endif
        </div>

        {{-- Overview Metrics Cards (Visible to Managers & Staff only) --}}
        @if($this->isStaff)
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6 sm:mb-8">
                <x-crud.panel class="p-4 sm:p-5 border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs min-w-0 overflow-hidden">
                    <flux:text size="xs" class="uppercase tracking-wider font-semibold text-zinc-500 dark:text-zinc-400 truncate">
                        {{ __('Monitored Routes') }}
                    </flux:text>
                    <div class="flex flex-wrap items-baseline gap-2 mt-2">
                        <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-zinc-100 truncate">
                            {{ number_format($this->summaryStats['totalRoutes']) }}
                        </flux:heading>
                        <flux:text size="xs" class="text-zinc-500 shrink-0">{{ __('active routes') }}</flux:text>
                    </div>
                </x-crud.panel>

                <x-crud.panel class="p-4 sm:p-5 border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs min-w-0 overflow-hidden">
                    <flux:text size="xs" class="uppercase tracking-wider font-semibold text-zinc-500 dark:text-zinc-400 truncate">
                        {{ __('Network Average') }}
                    </flux:text>
                    <div class="flex flex-wrap items-baseline gap-2 mt-2">
                        <flux:heading size="xl" class="font-bold text-emerald-600 dark:text-emerald-400 truncate">
                            ${{ number_format($this->summaryStats['avgNetworkRate'], 2) }}
                        </flux:heading>
                        <flux:text size="xs" class="text-zinc-500 shrink-0">{{ __('per vehicle tow') }}</flux:text>
                    </div>
                </x-crud.panel>

                <x-crud.panel class="p-4 sm:p-5 border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs min-w-0 overflow-hidden">
                    <flux:text size="xs" class="uppercase tracking-wider font-semibold text-zinc-500 dark:text-zinc-400 truncate">
                        {{ __('Network Price Range') }}
                    </flux:text>
                    <div class="flex items-baseline gap-1 mt-2 min-w-0">
                        <flux:heading size="lg" class="text-base sm:text-lg font-bold text-zinc-900 dark:text-zinc-100 truncate">
                            ${{ number_format($this->summaryStats['minNetworkRate'], 0) }} -
                            ${{ number_format($this->summaryStats['maxNetworkRate'], 0) }}
                        </flux:heading>
                    </div>
                </x-crud.panel>

                <x-crud.panel class="p-4 sm:p-5 border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-xs min-w-0 overflow-hidden">
                    <flux:text size="xs" class="uppercase tracking-wider font-semibold text-zinc-500 dark:text-zinc-400 truncate">
                        {{ __('Historical Invoices') }}
                    </flux:text>
                    <div class="flex flex-wrap items-baseline gap-2 mt-2">
                        <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-zinc-100 truncate">
                            {{ number_format($this->summaryStats['totalShipments']) }}
                        </flux:heading>
                        <flux:text size="xs" class="text-zinc-500 shrink-0">{{ __('invoices analyzed') }}</flux:text>
                    </div>
                </x-crud.panel>
            </div>
        @endif

        {{-- Interactive Rate Estimator & Line Chart Card --}}
        <div class="mb-8">
            <x-crud.panel
                class="p-4 sm:p-6 rounded-2xl border border-amber-200/70 dark:border-amber-900/40 bg-linear-to-br from-amber-50/40 via-white to-amber-50/20 dark:from-zinc-900 dark:via-zinc-900 dark:to-amber-950/20 shadow-sm overflow-hidden">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
                    <div class="flex items-center gap-2 min-w-0">
                        <flux:icon.calculator class="size-5 text-amber-600 dark:text-amber-400 shrink-0" />
                        <flux:heading size="lg" class="font-semibold text-base sm:text-lg truncate">
                            {{ __('Instant Rate Estimator & Trend Comparison') }}
                        </flux:heading>
                    </div>
                    @if($estimatorLocation !== '' || $estimatorPortId)
                        <flux:button variant="ghost" size="xs" icon="x-mark" wire:click="clearEstimator" class="self-start sm:self-auto shrink-0">
                            {{ __('Clear Estimator') }}
                        </flux:button>
                    @endif
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <flux:select wire:model.live="estimatorLocation" :label="__('Location')" searchable
                            placeholder="{{ __('Choose or search auction yard location...') }}">
                            <flux:select.option value="">{{ __('Select location...') }}</flux:select.option>
                            @foreach($this->pickupLocations as $loc)
                                <flux:select.option :value="$loc">
                                    {{ \App\Models\InlandRouteRate::formatLocation($loc) }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div>
                        <flux:select wire:model.live="estimatorPortId" :label="__('Destination Export Port')"
                            placeholder="{{ __('Choose US Export Port...') }}">
                            <flux:select.option value="">{{ __('Select Export Port...') }}</flux:select.option>
                            @foreach($this->availablePorts as $port)
                                <flux:select.option :value="$port->id">
                                    {{ $port->name }} ({{ $port->state?->code ?? 'US' }})
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>

                {{-- Estimator Result Output & Line Chart --}}
                @if($this->estimatedRate)
                    <div
                        class="rounded-xl border border-amber-200 dark:border-amber-800/60 bg-white/80 dark:bg-zinc-800/80 p-3.5 sm:p-5 backdrop-blur-xs transition-all overflow-hidden">
                        <div
                            class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-700">
                            <div class="min-w-0">
                                <div
                                    class="flex flex-wrap items-center gap-1.5 sm:gap-2 text-zinc-500 dark:text-zinc-400 text-xs font-medium uppercase tracking-wider">
                                    <span>{{ __('Verified Route Benchmark') }}</span>
                                    <span>&bull;</span>
                                    <span>{{ $this->estimatedRate->shipment_count }}
                                        {{ __('past shipments on record') }}</span>
                                </div>
                                <div class="text-base sm:text-lg font-bold text-zinc-900 dark:text-zinc-100 mt-1 break-words">
                                    {{ $this->estimatedRate->formatted_location }} &rarr;
                                    {{ $this->estimatedRate->originPort?->name }}
                                </div>
                            </div>

                            <div class="flex items-center gap-3 shrink-0 self-start sm:self-auto">
                                @php
                                    $trend = $this->estimatedRate->trend_percentage;
                                @endphp
                                @if($trend > 0)
                                    <flux:badge color="rose" variant="subtle" size="sm" class="sm:size-md" icon="arrow-trending-up">
                                        +{{ $trend }}% {{ __('vs historical avg') }}
                                    </flux:badge>
                                @elseif($trend < 0)
                                    <flux:badge color="emerald" variant="subtle" size="sm" class="sm:size-md" icon="arrow-trending-down">
                                        {{ $trend }}% {{ __('vs historical avg') }}
                                    </flux:badge>
                                @else
                                    <flux:badge color="zinc" variant="subtle" size="sm" class="sm:size-md" icon="minus">
                                        {{ __('Stable with historical avg') }}
                                    </flux:badge>
                                @endif
                            </div>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 pt-4">
                            <div class="min-w-0">
                                <flux:text size="xs" class="text-zinc-500 font-medium uppercase truncate">{{ __('Current Rate') }}
                                </flux:text>
                                <div class="text-xl sm:text-2xl font-black text-amber-600 dark:text-amber-400 mt-1 truncate">
                                    ${{ number_format((float) $this->estimatedRate->latest_rate, 2) }}
                                </div>
                                <flux:text size="xs" class="text-zinc-400 truncate">{{ __('Latest invoice') }}</flux:text>
                            </div>

                            <div class="min-w-0">
                                <flux:text size="xs" class="text-zinc-500 font-medium uppercase truncate">{{ __('Route Average') }}
                                </flux:text>
                                <div class="text-xl sm:text-2xl font-black text-zinc-800 dark:text-zinc-200 mt-1 truncate">
                                    ${{ number_format((float) $this->estimatedRate->average_rate, 2) }}
                                </div>
                                <flux:text size="xs" class="text-zinc-400 truncate">{{ __('Historical mean') }}</flux:text>
                            </div>

                            <div class="min-w-0">
                                <flux:text size="xs" class="text-zinc-500 font-medium uppercase truncate">{{ __('Historic Range') }}
                                </flux:text>
                                <div class="text-base sm:text-lg font-bold text-zinc-700 dark:text-zinc-300 mt-1 truncate">
                                    ${{ number_format((float) $this->estimatedRate->min_rate, 0) }} &ndash;
                                    ${{ number_format((float) $this->estimatedRate->max_rate, 0) }}
                                </div>
                                <flux:text size="xs" class="text-zinc-400 truncate">{{ __('Observed min to max') }}</flux:text>
                            </div>

                            <div class="min-w-0">
                                <flux:text size="xs" class="text-zinc-500 font-medium uppercase truncate">{{ __('Last Shipped') }}
                                </flux:text>
                                <div class="text-sm sm:text-base font-semibold text-zinc-700 dark:text-zinc-300 mt-1 truncate">
                                    {{ $this->estimatedRate->last_shipped_at ? $this->estimatedRate->last_shipped_at->format('M d, Y') : '—' }}
                                </div>
                                <flux:text size="xs" class="text-zinc-400 truncate">{{ __('Last invoice date') }}</flux:text>
                            </div>
                        </div>

                        {{-- Interactive SVG Line Chart Visualizing Price Movement --}}
                        @if($this->chartData)
                            <div class="mt-6 pt-5 border-t border-zinc-200 dark:border-zinc-700/80">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
                                    <div class="flex items-center gap-2">
                                        <flux:icon.chart-bar class="size-4 text-amber-600 dark:text-amber-400 shrink-0" />
                                        <flux:heading size="sm" weight="semibold">{{ __('Price Movement History & Trend') }}
                                        </flux:heading>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-3 sm:gap-4 text-xs text-zinc-500">
                                        <div class="flex items-center gap-1.5 shrink-0">
                                            <span class="inline-block size-2 rounded-full bg-amber-500"></span>
                                            <span>{{ __('Towing Fee ($)') }}</span>
                                        </div>
                                        <div class="flex items-center gap-1.5 shrink-0">
                                            <span class="inline-block w-3 border-b-2 border-dashed border-zinc-400"></span>
                                            <span>{{ __('Route Avg') }}
                                                (${{ number_format($this->chartData['avg'], 2) }})</span>
                                        </div>
                                    </div>
                                </div>

                                @if($this->chartData['count'] > 1)
                                    <div
                                        class="relative w-full rounded-xl bg-zinc-50/80 dark:bg-zinc-900/60 p-3 sm:p-4 border border-zinc-200/70 dark:border-zinc-800 overflow-hidden">
                                        <svg viewBox="0 0 540 150" class="w-full h-36 sm:h-44 overflow-hidden" preserveAspectRatio="none">
                                            <defs>
                                                <linearGradient id="inlandAreaGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                                                    <stop offset="0%" stop-color="#f59e0b" stop-opacity="0.30" />
                                                    <stop offset="100%" stop-color="#f59e0b" stop-opacity="0.0" />
                                                </linearGradient>
                                            </defs>

                                            <!-- Top Guide Line (Max) -->
                                            <line x1="35" y1="25" x2="505" y2="25" stroke="currentColor"
                                                class="text-zinc-200 dark:text-zinc-800" stroke-width="1" stroke-dasharray="2 2" />
                                            <!-- Bottom Guide Line (Min) -->
                                            <line x1="35" y1="125" x2="505" y2="125" stroke="currentColor"
                                                class="text-zinc-200 dark:text-zinc-800" stroke-width="1" stroke-dasharray="2 2" />

                                            <!-- Average Route Reference Line -->
                                            <line x1="35" y1="{{ $this->chartData['avgY'] }}" x2="505"
                                                y2="{{ $this->chartData['avgY'] }}" stroke="#9ca3af" stroke-dasharray="4 4"
                                                stroke-width="1.5" opacity="0.75" />

                                            <!-- Gradient Area Fill -->
                                            <path d="{{ $this->chartData['areaPath'] }}" fill="url(#inlandAreaGrad)" />

                                            <!-- Main Trend Polyline -->
                                            <polyline fill="none" stroke="#d97706" stroke-width="2.5" stroke-linecap="round"
                                                stroke-linejoin="round" points="{{ $this->chartData['polyline'] }}" />

                                            <!-- Data Point Circles with Tooltips -->
                                            @foreach($this->chartData['coordinates'] as $pt)
                                                <g class="cursor-pointer">
                                                    <circle cx="{{ $pt['x'] }}" cy="{{ $pt['y'] }}" r="4.5"
                                                        class="fill-white dark:fill-zinc-900 stroke-amber-600 stroke-2 hover:r-6 hover:fill-amber-600 transition-all">
                                                        <title>{{ $pt['formatted_date'] }}: ${{ number_format($pt['amount'], 2) }}
                                                        </title>
                                                    </circle>
                                                </g>
                                            @endforeach
                                        </svg>

                                        <div
                                            class="flex flex-wrap items-center justify-between gap-1 text-[11px] text-zinc-500 font-medium px-1 sm:px-2 mt-2">
                                            <span class="truncate">{{ $this->chartData['startDate'] }}</span>
                                            <span
                                                class="text-amber-600 dark:text-amber-400 font-semibold shrink-0">
                                                <span class="sm:hidden">{{ $this->chartData['count'] }} {{ __('pts') }}</span>
                                                <span class="hidden sm:inline">{{ $this->chartData['count'] }} {{ __('recorded price points') }}</span>
                                            </span>
                                            <span class="truncate">{{ $this->chartData['endDate'] }}</span>
                                        </div>
                                    </div>
                                @else
                                    <div
                                        class="rounded-lg bg-zinc-50 dark:bg-zinc-900/60 p-3 sm:p-4 border border-zinc-200 dark:border-zinc-800 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 overflow-hidden">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <div class="size-2.5 rounded-full bg-amber-500 shrink-0"></div>
                                            <div class="text-xs text-zinc-700 dark:text-zinc-300 truncate">
                                                <span class="font-semibold">${{ number_format($this->chartData['min'], 2) }}</span>
                                                <span class="text-zinc-500">({{ __('Recorded on') }}
                                                    {{ $this->chartData['startDate'] }})</span>
                                            </div>
                                        </div>
                                        <flux:badge size="xs" color="zinc" variant="subtle" class="self-start sm:self-auto shrink-0">
                                            {{ __('Initial benchmark established') }}
                                        </flux:badge>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @elseif($estimatorLocation !== '' && $estimatorPortId)
                    <div
                        class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-800/40 p-4 sm:p-5 text-center">
                        <flux:icon.exclamation-circle class="size-6 text-zinc-400 mx-auto mb-2" />
                        <div class="font-medium text-sm text-zinc-800 dark:text-zinc-200">
                            {{ __('No historical direct shipments found for this specific route.') }}
                        </div>
                        <flux:text size="xs" class="text-zinc-500 mt-1">
                            {{ __('Check nearby export ports or review the full pricing matrix below for comparable rates.') }}
                        </flux:text>
                    </div>
                @else
                    <div
                        class="rounded-xl border border-dashed border-zinc-300 dark:border-zinc-800 p-4 text-center text-xs text-zinc-500">
                        {{ __('Select a location and export port above, or click any route in the table below to load its metrics.') }}
                    </div>
                @endif
            </x-crud.panel>
        </div>

        {{-- Filters & Search --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 mb-4">
            <div class="flex-1 w-full max-w-md">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                    placeholder="{{ __('Search by pickup location or port...') }}" clearable class="w-full" />
            </div>

            <div class="flex items-center gap-3 w-full sm:w-auto">
                <flux:select wire:model.live="filterPortId" placeholder="{{ __('Filter by Port...') }}" class="w-full sm:w-48">
                    <flux:select.option value="">{{ __('All Ports') }}</flux:select.option>
                    @foreach($this->availablePorts as $port)
                        <flux:select.option :value="$port->id">{{ $port->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        {{-- Route Rates Table --}}
        <x-crud.panel class="p-3 sm:p-6 overflow-hidden">
            <flux:table :paginate="$this->rates">
                <flux:table.columns sticky class="bg-white dark:bg-zinc-900">
                    <flux:table.column icon="map-pin">{{ __('Location') }}</flux:table.column>
                    <flux:table.column icon="globe-alt">{{ __('Export Port') }}</flux:table.column>
                    <flux:table.column align="end" icon="banknotes">
                        {{ $this->isStaff ? __('Latest Rate') : __('Current Rate') }}
                    </flux:table.column>
                    <flux:table.column align="end">{{ __('Average Rate') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Observed Range') }}</flux:table.column>
                    @if($this->isStaff)
                        <flux:table.column align="center">{{ __('Shipments') }}</flux:table.column>
                    @endif
                    <flux:table.column align="center">{{ __('Trend') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Action') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse($this->rates as $rate)
                        <flux:table.row :key="$rate->id"
                            class="{{ $estimatorLocation === $rate->pickup_location && $estimatorPortId === $rate->origin_port_id ? 'bg-amber-50/50 dark:bg-amber-950/20' : '' }}">
                            <flux:table.cell variant="strong">
                                <div class="flex items-center gap-2">
                                    <flux:icon.map-pin class="size-4 text-zinc-400 shrink-0" />
                                    <span class="truncate max-w-xs font-semibold">{{ $rate->formatted_location }}</span>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="flex items-center gap-1.5">
                                    <span class="size-2 rounded-full bg-cyan-500"></span>
                                    <span>{{ $rate->originPort?->name ?? '—' }}</span>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell align="end" class="font-bold text-amber-600 dark:text-amber-400">
                                ${{ number_format((float) $rate->latest_rate, 2) }}
                            </flux:table.cell>

                            <flux:table.cell align="end" class="font-medium text-zinc-800 dark:text-zinc-200">
                                ${{ number_format((float) $rate->average_rate, 2) }}
                            </flux:table.cell>

                            <flux:table.cell align="end" class="text-xs text-zinc-500">
                                ${{ number_format((float) $rate->min_rate, 0) }} &ndash;
                                ${{ number_format((float) $rate->max_rate, 0) }}
                            </flux:table.cell>

                            @if($this->isStaff)
                                <flux:table.cell align="center">
                                    <flux:badge color="zinc" variant="subtle" size="sm">
                                        {{ $rate->shipment_count }}
                                    </flux:badge>
                                </flux:table.cell>
                            @endif

                            <flux:table.cell align="center">
                                @php
                                    $trend = $rate->trend_percentage;
                                @endphp
                                @if($trend > 0)
                                    <flux:badge color="rose" variant="subtle" size="sm" icon="arrow-up-right">
                                        +{{ $trend }}%
                                    </flux:badge>
                                @elseif($trend < 0)
                                    <flux:badge color="emerald" variant="subtle" size="sm" icon="arrow-down-right">
                                        {{ $trend }}%
                                    </flux:badge>
                                @else
                                    <flux:badge color="zinc" variant="subtle" size="sm">
                                        0.0%
                                    </flux:badge>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell align="end">
                                <flux:button size="xs" variant="outline" icon="chart-bar"
                                    wire:click="selectRoute('{{ addslashes($rate->pickup_location) }}', {{ $rate->origin_port_id }})">
                                    {{ $this->isStaff ? __('Analyze') : __('Calculate') }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="{{ $this->isStaff ? 8 : 7 }}" class="py-12 text-center text-zinc-500">
                                <flux:icon.magnifying-glass class="size-6 mx-auto mb-2 opacity-50" />
                                {{ __('No inland route rates match your search.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </x-crud.panel>
    </x-crud.page-shell>
</div>