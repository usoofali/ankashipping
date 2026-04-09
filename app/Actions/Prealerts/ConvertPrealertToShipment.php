<?php

declare(strict_types=1);

namespace App\Actions\Prealerts;

use App\Actions\Shipments\MergeShipmentDefaults;
use App\Models\Prealert;
use App\Models\Shipment;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ConvertPrealertToShipment
{
    /**
     * Creates a shipment, assigns the prealert vehicle (1:1), and deletes the prealert row.
     *
     * @param  array<string, mixed>  $shipmentInput  Must include consignee and other required shipment fields; shipper_id defaults from the prealert.
     */
    public function execute(Prealert $prealert, array $shipmentInput): Shipment
    {
        return DB::transaction(function () use ($prealert, $shipmentInput): Shipment {
            $prealert->loadMissing('shipper.defaultConsignee');

            $vehicle = $prealert->vehicle;
            if (! $vehicle instanceof Vehicle || $vehicle->shipment()->exists()) {
                throw new \InvalidArgumentException(__('Prealert must reference a vehicle that is not yet assigned to a shipment.'));
            }

            $defaultConsigneeId = $prealert->shipper?->defaultConsignee?->id;
            if ($defaultConsigneeId === null) {
                throw new \InvalidArgumentException(__('A default consignee is required for the shipper before converting a prealert.'));
            }

            $shipmentInput = array_merge(
                $shipmentInput,
                [
                    'shipper_id' => $prealert->shipper_id,
                    'consignee_id' => $defaultConsigneeId,
                    'gatepass_pin' => $prealert->gatepass_pin,
                    'carrier_id' => $prealert->carrier_id,
                    'destination_port_id' => $prealert->destination_port_id,
                ],
            );

            if (
                ! array_key_exists('reference_no', $shipmentInput)
                || $shipmentInput['reference_no'] === null
                || $shipmentInput['reference_no'] === ''
            ) {
                $shipmentInput['reference_no'] = $this->uniqueReferenceNumber();
            }

            $attributes = MergeShipmentDefaults::merge($shipmentInput);
            $shipment = Shipment::query()->create($attributes);

            // Update relations: link vehicle to shipment, update prealert status
            $shipment->update(['vehicle_id' => $vehicle->id]);
            $prealert->delete();

            return $shipment->fresh() ?? $shipment;
        });
    }

    private function uniqueReferenceNumber(): string
    {
        do {
            $ref = 'REF-'.strtoupper(Str::random(10));
        } while (Shipment::query()->where('reference_no', $ref)->exists());

        return $ref;
    }
}
