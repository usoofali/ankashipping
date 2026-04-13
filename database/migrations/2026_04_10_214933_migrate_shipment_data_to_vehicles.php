<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Link Vehicles to Shipments and move logistical fields
        DB::table('shipments')->whereNotNull('vehicle_id')->orderBy('id')->chunk(100, function ($shipments) {
            foreach ($shipments as $shipment) {
                DB::table('vehicles')->where('id', $shipment->vehicle_id)->update([
                    'shipment_id' => $shipment->id,
                    'driver_id' => $shipment->driver_id,
                    'workshop_id' => $shipment->workshop_id,
                    'status_before_workshop' => $shipment->shipment_status_before_workshop,
                    // Note: auction_receipt is usually already on vehicle, but we can sync it just in case
                    'auction_receipt' => $shipment->auction_receipt,
                ]);
            }
        });

        // 2. Link Vehicles to Prealerts
        DB::table('prealerts')->whereNotNull('vehicle_id')->orderBy('id')->chunk(100, function ($prealerts) {
            foreach ($prealerts as $prealert) {
                DB::table('vehicles')->where('id', $prealert->vehicle_id)->update([
                    'prealert_id' => $prealert->id,
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reset the linked fields in vehicles
        DB::table('vehicles')->update([
            'shipment_id' => null,
            'prealert_id' => null,
            'driver_id' => null,
            'workshop_id' => null,
            'status_before_workshop' => null,
        ]);
    }
};
