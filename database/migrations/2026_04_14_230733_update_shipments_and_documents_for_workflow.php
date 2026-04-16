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
            $table->timestamp('completed_at')->nullable()->after('sealed_at');
            $table->timestamp('seal_closed_at')->nullable()->after('completed_at');
        });

        Schema::table('shipment_documents', function (Blueprint $table) {
            $table->foreignId('shipment_id')->nullable()->change();
            $table->foreignId('vehicle_id')->nullable()->after('shipment_id')->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipment_documents', function (Blueprint $table) {
            $table->dropForeign(['vehicle_id']);
            $table->dropColumn('vehicle_id');
            $table->foreignId('shipment_id')->nullable(false)->change();
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['completed_at', 'seal_closed_at']);
        });
    }
};
