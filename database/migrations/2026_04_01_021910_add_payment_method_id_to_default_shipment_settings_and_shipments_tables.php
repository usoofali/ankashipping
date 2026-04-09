<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('default_shipment_settings', function (Blueprint $table): void {
            $table->foreignId('payment_method_id')->nullable()->after('payment_status')->constrained()->nullOnDelete();
        });

        Schema::table('shipments', function (Blueprint $table): void {
            $table->foreignId('payment_method_id')->nullable()->after('payment_status')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payment_method_id');
        });

        Schema::table('default_shipment_settings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payment_method_id');
        });
    }
};
