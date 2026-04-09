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
        Schema::table('prealerts', function (Blueprint $table) {
            $table->foreignId('consignee_id')->nullable()->after('shipper_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prealerts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('consignee_id');
        });
    }
};
