<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add fields to vehicles
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('gatepass_pin', 11)->nullable()->after('lot_number');
        });

        // 2. Add fields to prealerts
        Schema::table('prealerts', function (Blueprint $table) {
            $table->string('shipping_mode')->default('roro')->after('carrier_id');
            $table->foreignId('shipment_id')->nullable()->after('shipping_mode')->constrained()->nullOnDelete();
        });

        // 3. Migrate existing gatepass_pin data
        // From prealerts to vehicles
        $prealerts = DB::table('prealerts')->whereNotNull('gatepass_pin')->get();
        foreach ($prealerts as $prealert) {
            DB::table('vehicles')
                ->where('prealert_id', $prealert->id)
                ->update(['gatepass_pin' => $prealert->gatepass_pin]);
        }

        // From shipments to vehicles
        $shipments = DB::table('shipments')->whereNotNull('gatepass_pin')->get();
        foreach ($shipments as $shipment) {
            DB::table('vehicles')
                ->where('shipment_id', $shipment->id)
                ->update(['gatepass_pin' => $shipment->gatepass_pin]);
        }

        // 4. Drop old columns
        Schema::table('prealerts', function (Blueprint $table) {
            $table->dropColumn('gatepass_pin');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn('gatepass_pin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('gatepass_pin', 11)->nullable();
        });

        Schema::table('prealerts', function (Blueprint $table) {
            $table->string('gatepass_pin', 11)->nullable();
        });

        // Restore data (best effort)
        $vehicles = DB::table('vehicles')->whereNotNull('gatepass_pin')->get();
        foreach ($vehicles as $vehicle) {
            if ($vehicle->prealert_id) {
                DB::table('prealerts')
                    ->where('id', $vehicle->prealert_id)
                    ->update(['gatepass_pin' => $vehicle->gatepass_pin]);
            }
            if ($vehicle->shipment_id) {
                DB::table('shipments')
                    ->where('id', $vehicle->shipment_id)
                    ->update(['gatepass_pin' => $vehicle->gatepass_pin]);
            }
        }

        Schema::table('prealerts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipment_id');
            $table->dropColumn('shipping_mode');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('gatepass_pin');
        });
    }
};
