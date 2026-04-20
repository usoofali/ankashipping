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
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('exporter_name')->nullable();
            $table->text('exporter_address')->nullable();
            $table->string('exporter_state')->nullable();
            $table->string('exporter_country')->nullable();
            $table->string('exporter_zipcode')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn([
                'exporter_name',
                'exporter_address',
                'exporter_state',
                'exporter_country',
                'exporter_zipcode',
            ]);
        });
    }
};
