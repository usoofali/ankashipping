<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('vin')->nullable()->index();
            $table->string('lot_number')->nullable()->index();
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->string('year')->nullable();
            $table->string('series')->nullable();
            $table->string('body_style')->nullable();
            $table->string('color')->nullable();
            $table->string('vehicle_type')->nullable();
            $table->string('transmission')->nullable();
            $table->string('fuel')->nullable();
            $table->string('engine_type')->nullable();
            $table->string('drive')->nullable();
            $table->unsignedInteger('cylinders')->nullable();
            $table->unsignedBigInteger('odometer')->nullable();
            $table->string('car_keys')->nullable();
            $table->string('doc_type')->nullable();
            $table->string('primary_damage')->nullable();
            $table->string('secondary_damage')->nullable();
            $table->string('highlights')->nullable();
            $table->string('location')->nullable();
            $table->string('auction_name')->nullable();
            $table->string('seller')->nullable();
            $table->decimal('est_retail_value', 14, 2)->nullable();
            $table->boolean('is_insurance')->default(false);
            $table->unsignedBigInteger('currency_code_id')->nullable();
            $table->json('api_snapshot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
