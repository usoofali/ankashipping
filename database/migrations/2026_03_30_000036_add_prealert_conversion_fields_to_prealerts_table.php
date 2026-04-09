<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prealerts', function (Blueprint $table) {
            $table->string('gatepass_pin', 11)->nullable()->after('vin');
            $table->foreignId('carrier_id')->nullable()->after('vehicle_id')->constrained()->nullOnDelete();
            $table->foreignId('destination_port_id')->nullable()->after('carrier_id')->constrained('ports')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('prealerts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('destination_port_id');
            $table->dropConstrainedForeignId('carrier_id');
            $table->dropColumn('gatepass_pin');
        });
    }
};
