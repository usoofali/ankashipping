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
        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignId('notify_party_id')->nullable()->constrained('consignees')->nullOnDelete();
        });

        Schema::table('prealerts', function (Blueprint $table) {
            $table->foreignId('notify_party_id')->nullable()->constrained('consignees')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropForeign(['notify_party_id']);
            $table->dropColumn('notify_party_id');
        });

        Schema::table('prealerts', function (Blueprint $table) {
            $table->dropForeign(['notify_party_id']);
            $table->dropColumn('notify_party_id');
        });
    }
};
