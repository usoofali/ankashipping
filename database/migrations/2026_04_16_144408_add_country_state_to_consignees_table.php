<?php

use App\Models\Country;
use App\Models\State;
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
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete()->after('address');
            $table->foreignId('state_id')->nullable()->constrained()->nullOnDelete()->after('country_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consignees', function (Blueprint $table) {
            $table->dropForeignIdFor(Country::class);
            $table->dropForeignIdFor(State::class);
        });
    }
};
