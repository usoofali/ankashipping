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
        Schema::table('ports', function (Blueprint $table) {
            $table->string('type')->default('destination')->after('name'); // origin or destination
            $table->dropForeign(['city_id']);
            $table->dropIndex(['code']);
            $table->dropColumn(['code', 'city_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ports', function (Blueprint $table) {
            $table->string('code')->nullable()->index()->after('name');
            $table->foreignId('city_id')->nullable()->constrained()->cascadeOnDelete()->after('state_id');
            $table->dropColumn('type');
        });
    }
};
