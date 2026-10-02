<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InlandRouteRate;
use App\Models\Port;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class InlandRateService
{
    /**
     * Aggregate all historical inland invoice charges and update the inland_route_rates snapshot table.
     */
    public function syncFromHistoricalInvoices(): int
    {
        $rawRecords = DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('shipments', 'invoices.shipment_id', '=', 'shipments.id')
            ->join('vehicles', 'vehicles.shipment_id', '=', 'shipments.id')
            ->whereRaw('LOWER(invoice_items.description) LIKE ?', ['%inland%'])
            ->whereNotNull('shipments.origin_port_id')
            ->whereNotNull('vehicles.location')
            ->whereRaw('TRIM(vehicles.location) != ""')
            ->where('invoice_items.amount', '>', 0)
            ->select([
                'shipments.id as shipment_id',
                'shipments.origin_port_id',
                DB::raw('LOWER(TRIM(vehicles.location)) as pickup_location'),
                'invoice_items.amount as amount',
                DB::raw('COALESCE(invoices.created_at, shipments.created_at) as shipped_at'),
            ])
            ->distinct()
            ->orderBy('shipped_at')
            ->get();

        $routes = $rawRecords->groupBy(function ($item): string {
            return $item->pickup_location.'___'.$item->origin_port_id;
        });

        $now = Carbon::now();
        $upsertData = [];

        foreach ($routes as $group) {
            $first = $group->first();
            $amounts = $group->pluck('amount')->map(fn ($val) => (float) $val);

            $latestRecord = $group->sortByDesc('shipped_at')->first();
            $latestRate = (float) ($latestRecord?->amount ?? $amounts->last());
            $avgRate = round($amounts->average(), 2);
            $minRate = (float) $amounts->min();
            $maxRate = (float) $amounts->max();
            $shipmentCount = $group->pluck('shipment_id')->unique()->count();
            $lastShippedAt = $latestRecord?->shipped_at ? Carbon::parse($latestRecord->shipped_at) : null;

            $upsertData[] = [
                'pickup_location' => (string) $first->pickup_location,
                'origin_port_id' => (int) $first->origin_port_id,
                'latest_rate' => $latestRate,
                'average_rate' => $avgRate,
                'min_rate' => $minRate,
                'max_rate' => $maxRate,
                'shipment_count' => $shipmentCount,
                'last_shipped_at' => $lastShippedAt?->toDateTimeString(),
                'updated_at' => $now->toDateTimeString(),
                'created_at' => $now->toDateTimeString(),
            ];
        }

        if (! empty($upsertData)) {
            InlandRouteRate::upsert(
                $upsertData,
                ['pickup_location', 'origin_port_id'],
                ['latest_rate', 'average_rate', 'min_rate', 'max_rate', 'shipment_count', 'last_shipped_at', 'updated_at']
            );
        }

        return count($upsertData);
    }

    /**
     * Get rate metrics for a given pickup location and export port.
     */
    public function getRateForRoute(string $pickupLocation, int $originPortId): ?InlandRouteRate
    {
        $normalizedLocation = strtolower(trim($pickupLocation));

        return InlandRouteRate::query()
            ->with('originPort')
            ->where('pickup_location', $normalizedLocation)
            ->where('origin_port_id', $originPortId)
            ->first();
    }

    /**
     * Get distinct pickup locations from existing rates and vehicles.
     *
     * @return Collection<int, string>
     */
    public function getDistinctPickupLocations(): Collection
    {
        return InlandRouteRate::query()
            ->select('pickup_location')
            ->distinct()
            ->orderBy('pickup_location')
            ->pluck('pickup_location')
            ->values();
    }

    /**
     * Get ports that have historical inland routes.
     *
     * @return Collection<int, Port>
     */
    public function getAvailableOriginPorts(): Collection
    {
        return Port::query()
            ->whereHas('inlandRouteRates')
            ->orWhere('type', 'origin')
            ->with(['state', 'country'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Get historical rate data points over time for a given route.
     *
     * @return Collection<int, object{date: string, amount: float, reference_no: ?string}>
     */
    public function getRouteHistoryPoints(string $pickupLocation, int $originPortId): Collection
    {
        $normalizedLocation = strtolower(trim($pickupLocation));

        return DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('shipments', 'invoices.shipment_id', '=', 'shipments.id')
            ->join('vehicles', 'vehicles.shipment_id', '=', 'shipments.id')
            ->whereRaw('LOWER(invoice_items.description) LIKE ?', ['%inland%'])
            ->where('shipments.origin_port_id', $originPortId)
            ->whereRaw('LOWER(TRIM(vehicles.location)) = ?', [$normalizedLocation])
            ->where('invoice_items.amount', '>', 0)
            ->select([
                'shipments.id as shipment_id',
                'shipments.reference_no',
                'invoice_items.amount',
                DB::raw('DATE(COALESCE(invoices.created_at, shipments.created_at)) as date'),
            ])
            ->distinct()
            ->orderBy('date')
            ->get()
            ->groupBy('date')
            ->map(function ($group, string $date) {
                return (object) [
                    'date' => $date,
                    'amount' => round((float) $group->avg('amount'), 2),
                    'count' => $group->count(),
                    'reference_no' => $group->pluck('reference_no')->filter()->first(),
                ];
            })
            ->values();
    }
}
