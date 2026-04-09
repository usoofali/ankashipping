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
        Schema::table('consignees', function (Blueprint $table) {
            if (! Schema::hasTable('consignees')) {
                return;
            }

            $table->dropColumn('contact');
            $table->dropColumn('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consignees', function (Blueprint $table) {
            if (! Schema::hasTable('consignees')) {
                return;
            }

            $table->string('contact')->nullable();
            $table->string('phone')->nullable();
        });
    }
};
