<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['shipment_id']);
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropUnique(['shipment_id']);
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreignId('shipment_id')->nullable()->change();
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreign('shipment_id')
                ->references('id')
                ->on('shipments')
                ->cascadeOnDelete();
            $table->unique('shipment_id');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->timestamp('api_fetched_at')->nullable()->after('api_snapshot');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->unique('vin');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropUnique(['vin']);
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('api_fetched_at');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['shipment_id']);
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropUnique(['shipment_id']);
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreignId('shipment_id')->nullable(false)->change();
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreign('shipment_id')
                ->references('id')
                ->on('shipments')
                ->cascadeOnDelete();
            $table->unique('shipment_id');
        });
    }
};
