<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CarrierReleaseData;
use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Vehicle;
use App\Modules\WhatsApp\Services\TelexRequestService;
use Illuminate\Support\Facades\Log;

class CarrierReleaseFulfillmentService
{
    public function __construct(
        protected TelexRequestService $telexRequestService,
    ) {}

    /**
     * Process a CarrierReleaseData payload and fulfill if shipment is in TELEX_REQUESTED status.
     *
     * @return array{
     *     status: 'fulfilled'|'skipped'|'error',
     *     reason?: string,
     *     shipment?: Shipment,
     *     message: string,
     *     mark_as_seen: bool,
     * }
     */
    public function processRelease(CarrierReleaseData $data, bool $dryRun = false): array
    {
        $vin = strtoupper(trim($data->vin));

        // 1. Find Vehicle by VIN
        $vehicle = Vehicle::findByVin($vin);
        if (! $vehicle) {
            Log::info("CarrierReleaseFulfillmentService: VIN {$vin} not found in database. Email will remain unread.");

            return [
                'status' => 'skipped',
                'reason' => 'unknown_vin',
                'message' => "VIN {$vin} not found in database.",
                'mark_as_seen' => false,
            ];
        }

        // 2. Resolve Associated Shipment
        $shipment = $vehicle->shipment;
        if (! $shipment) {
            Log::info("CarrierReleaseFulfillmentService: Vehicle ID {$vehicle->id} (VIN: {$vin}) has no associated shipment. Email will remain unread.");

            return [
                'status' => 'skipped',
                'reason' => 'no_shipment',
                'message' => "Vehicle {$vin} exists but has no active shipment.",
                'mark_as_seen' => false,
            ];
        }

        // 3. Strict Status Guard: Must be strictly TELEX_REQUESTED
        if ($shipment->shipment_status !== ShipmentStatus::TelexRequested) {
            Log::info("CarrierReleaseFulfillmentService: Skipping {$shipment->reference_no} (VIN: {$vin}) - current status is '{$shipment->shipment_status->value}', expected 'TELEX_REQUESTED'. Email will remain unread.");

            return [
                'status' => 'skipped',
                'reason' => 'status_not_telex_requested',
                'shipment' => $shipment,
                'message' => "Shipment {$shipment->reference_no} is currently '{$shipment->shipment_status->value}'. Telex fulfillment skipped until status is TELEX_REQUESTED.",
                'mark_as_seen' => false,
            ];
        }

        // 4. Dry-Run Mode Check
        if ($dryRun) {
            return [
                'status' => 'fulfilled',
                'shipment' => $shipment,
                'message' => "[DRY RUN] Would fulfill telex release for {$shipment->reference_no} (VIN: {$vin}) via {$data->carrier}.",
                'mark_as_seen' => false,
            ];
        }

        // 5. Fulfill Telex Release
        try {
            // Update Bill of Lading number if shipment does not have one yet
            if (empty($shipment->bill_of_lading_number) && ! empty($data->blNumber)) {
                $shipment->update(['bill_of_lading_number' => $data->blNumber]);
            }

            // Find an admin user to attribute the activity log, if available
            $systemUser = User::query()
                ->whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))
                ->first() ?? User::first();

            $this->telexRequestService->fulfillTelexRelease(
                $shipment,
                $data->officialReleaseText,
                $systemUser,
                'carrier_email_automation'
            );

            Log::info("CarrierReleaseFulfillmentService: Successfully fulfilled release for {$shipment->reference_no} (VIN: {$vin}) from {$data->carrier}.");

            return [
                'status' => 'fulfilled',
                'shipment' => $shipment,
                'message' => "Successfully fulfilled release for {$shipment->reference_no} (VIN: {$vin}) from carrier {$data->carrier}.",
                'mark_as_seen' => true,
            ];
        } catch (\Throwable $e) {
            Log::error("CarrierReleaseFulfillmentService: Error fulfilling release for {$shipment->reference_no}: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return [
                'status' => 'error',
                'reason' => 'exception',
                'shipment' => $shipment,
                'message' => "Error fulfilling release for {$shipment->reference_no}: {$e->getMessage()}",
                'mark_as_seen' => false,
            ];
        }
    }
}
