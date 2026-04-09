<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('email')->nullable()->after('phone');
            $table->string('company')->nullable()->after('email');
            $table->dropColumn('license_number');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn(['email', 'company']);
            $table->string('license_number')->nullable()->after('phone');
        });
    }
};
