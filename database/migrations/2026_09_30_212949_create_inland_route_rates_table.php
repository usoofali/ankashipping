<?php

declare(strict_types=1);

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
        Schema::create('inland_route_rates', function (Blueprint $table): void {
            $table->id();
            $table->string('pickup_location')->index();
            $table->foreignId('origin_port_id')->constrained('ports')->cascadeOnDelete();
            $table->decimal('latest_rate', 10, 2)->default(0.00);
            $table->decimal('average_rate', 10, 2)->default(0.00);
            $table->decimal('min_rate', 10, 2)->default(0.00);
            $table->decimal('max_rate', 10, 2)->default(0.00);
            $table->unsignedInteger('shipment_count')->default(0);
            $table->timestamp('last_shipped_at')->nullable();
            $table->timestamps();

            $table->unique(['pickup_location', 'origin_port_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inland_route_rates');
    }
};
