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
            $table->unique('phone');
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->unique('phone');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->unique('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shippers', function (Blueprint $table) {
            $table->dropUnique(['phone']);
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->dropUnique(['phone']);
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->dropUnique(['phone']);
        });
    }
};
