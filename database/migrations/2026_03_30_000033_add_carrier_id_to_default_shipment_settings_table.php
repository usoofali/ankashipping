<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('default_shipment_settings', function (Blueprint $table) {
            $table->foreignId('carrier_id')->nullable()->after('shipping_company_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('default_shipment_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('carrier_id');
        });
    }
};
