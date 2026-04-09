<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            $table->foreignId('workshop_id')
                ->nullable()
                ->after('payment_method_id')
                ->constrained('workshops')
                ->nullOnDelete();
            $table->string('shipment_status_before_workshop')->nullable()->after('workshop_id');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropForeign(['workshop_id']);
            $table->dropColumn(['workshop_id', 'shipment_status_before_workshop']);
        });
    }
};
