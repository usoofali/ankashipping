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
        Schema::table('system_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('system_settings', 'fmc_number')) {
                $table->string('fmc_number')->nullable();
            }
            if (! Schema::hasColumn('system_settings', 'forwarding_agent_name')) {
                $table->string('forwarding_agent_name')->nullable();
            }
            if (! Schema::hasColumn('system_settings', 'forwarding_agent_address')) {
                $table->string('forwarding_agent_address')->nullable();
            }
            if (! Schema::hasColumn('system_settings', 'forwarding_agent_phone')) {
                $table->string('forwarding_agent_phone')->nullable();
            }

            // Cleanup old name if it exists
            if (Schema::hasColumn('system_settings', 'fmc_no')) {
                $table->dropColumn('fmc_no');
            }
        });

        Schema::table('shipments', function (Blueprint $table) {
            if (! Schema::hasColumn('shipments', 'bill_of_lading_number')) {
                $table->string('bill_of_lading_number')->nullable();
            }
            if (! Schema::hasColumn('shipments', 'booking_number')) {
                $table->string('booking_number')->nullable();
            }
            if (! Schema::hasColumn('shipments', 'itn_number')) {
                $table->string('itn_number')->nullable();
            }
            if (! Schema::hasColumn('shipments', 'container_no')) {
                $table->string('container_no')->nullable();
            }
            if (! Schema::hasColumn('shipments', 'seal_no')) {
                $table->string('seal_no')->nullable();
            }
            if (! Schema::hasColumn('shipments', 'container_type')) {
                $table->string('container_type')->nullable();
            }
            if (! Schema::hasColumn('shipments', 'vessel_name')) {
                $table->string('vessel_name')->nullable();
            }
            if (! Schema::hasColumn('shipments', 'voyage_no')) {
                $table->string('voyage_no')->nullable();
            }
            if (! Schema::hasColumn('shipments', 'cut_off_date')) {
                $table->date('cut_off_date')->nullable();
            }
            if (! Schema::hasColumn('shipments', 'departure_date')) {
                $table->date('departure_date')->nullable();
            }
            if (! Schema::hasColumn('shipments', 'arrival_date')) {
                $table->date('arrival_date')->nullable();
            }

            // Cleanup old names
            if (Schema::hasColumn('shipments', 'bl_no')) {
                $table->dropColumn('bl_no');
            }
            if (Schema::hasColumn('shipments', 'booking_no')) {
                $table->dropColumn('booking_no');
            }
            if (Schema::hasColumn('shipments', 'aes_itn')) {
                $table->dropColumn('aes_itn');
            }
        });

        Schema::table('vehicles', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicles', 'value')) {
                $table->decimal('value', 14, 2)->nullable();
            }
            if (! Schema::hasColumn('vehicles', 'weight')) {
                $table->decimal('weight', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('vehicles', 'weight_unit')) {
                $table->string('weight_unit')->default('KG');
            }
            if (! Schema::hasColumn('vehicles', 'measurement')) {
                $table->decimal('measurement', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('vehicles', 'measurement_unit')) {
                $table->string('measurement_unit')->default('CBM');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $cols = ['value', 'weight', 'weight_unit', 'measurement', 'measurement_unit'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('vehicles', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('shipments', function (Blueprint $table) {
            $cols = [
                'bill_of_lading_number', 'booking_number', 'itn_number', 'container_no',
                'seal_no', 'container_type', 'vessel_name', 'voyage_no',
                'cut_off_date', 'departure_date', 'arrival_date',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('shipments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('system_settings', function (Blueprint $table) {
            $cols = ['fmc_number', 'forwarding_agent_name', 'forwarding_agent_address', 'forwarding_agent_phone'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('system_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
