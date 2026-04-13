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
        Schema::table('prealerts', function (Blueprint $table) {
            $table->dropForeign(['vehicle_id']);
            $table->dropUnique('prealerts_vehicle_id_unique');
            $table->dropUnique('prealerts_vin_unique');
            $table->dropIndex('prealerts_vin_index');
            $table->dropColumn(['vin', 'vehicle_id', 'auction_receipt']);
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropForeign(['vehicle_id']);
            $table->dropForeign(['driver_id']);
            $table->dropForeign(['workshop_id']);

            $table->dropColumn([
                'vin',
                'vehicle_id',
                'auction_receipt',
                'driver_id',
                'workshop_id',
                'shipment_status_before_workshop',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('vin')->nullable();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->string('auction_receipt')->nullable();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('workshop_id')->nullable()->constrained('workshops')->nullOnDelete();
            $table->string('shipment_status_before_workshop')->nullable();
        });

        Schema::table('prealerts', function (Blueprint $table) {
            $table->string('vin')->nullable()->unique()->index();
            $table->foreignId('vehicle_id')->nullable()->unique()->constrained('vehicles')->nullOnDelete();
            $table->string('auction_receipt')->nullable();
        });
    }
};
