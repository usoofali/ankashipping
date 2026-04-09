<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('default_shipment_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_company_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('origin_port_id')->nullable()->constrained('ports')->restrictOnDelete();
            $table->foreignId('destination_port_id')->nullable()->constrained('ports')->restrictOnDelete();
            $table->string('logistics_service')->nullable();
            $table->string('shipping_mode')->nullable();
            $table->string('status')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('default_shipment_settings');
    }
};
