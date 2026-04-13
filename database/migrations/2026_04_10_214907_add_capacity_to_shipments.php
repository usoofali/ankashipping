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
            if (! Schema::hasColumn('shipments', 'capacity')) {
                $table->integer('capacity')->default(1)->after('shipping_mode');
            }
            if (! Schema::hasColumn('shipments', 'sealed_at')) {
                $table->timestamp('sealed_at')->nullable()->after('shipment_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['capacity', 'sealed_at']);
        });
    }
};
