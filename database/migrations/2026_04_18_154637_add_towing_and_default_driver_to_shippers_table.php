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
        Schema::table('shippers', function (Blueprint $table) {
            $table->boolean('towing')->default(true);
            $table->foreignId('default_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shippers', function (Blueprint $table) {
            $table->dropForeign(['default_driver_id']);
            $table->dropColumn(['towing', 'default_driver_id']);
        });
    }
};
