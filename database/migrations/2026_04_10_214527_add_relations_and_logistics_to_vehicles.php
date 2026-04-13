<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicles', 'shipment_id')) {
                $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('vehicles', 'prealert_id')) {
                $table->foreignId('prealert_id')->nullable()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('vehicles', 'driver_id')) {
                $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('vehicles', 'workshop_id')) {
                $table->foreignId('workshop_id')->nullable()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('vehicles', 'tracking_status')) {
                $table->string('tracking_status')->nullable()->after('vin');
            }
            if (! Schema::hasColumn('vehicles', 'status_before_workshop')) {
                $table->string('status_before_workshop')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['shipment_id']);
            $table->dropForeign(['prealert_id']);
            $table->dropForeign(['driver_id']);
            $table->dropForeign(['workshop_id']);
            $table->dropColumn(['shipment_id', 'prealert_id', 'driver_id', 'workshop_id', 'tracking_status', 'status_before_workshop']);
        });
    }
};
