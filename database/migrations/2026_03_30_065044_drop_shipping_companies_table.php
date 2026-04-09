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
            $table->dropConstrainedForeignId('shipping_company_id');
        });

        Schema::table('default_shipment_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipping_company_id');
        });

        Schema::dropIfExists('shipping_companies');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('shipping_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignId('shipping_company_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('default_shipment_settings', function (Blueprint $table) {
            $table->foreignId('shipping_company_id')->nullable()->constrained()->nullOnDelete();
        });
    }
};
