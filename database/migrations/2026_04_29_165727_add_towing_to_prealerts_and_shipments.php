<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prealerts', function (Blueprint $table) {
            $table->boolean('towing')->default(false)->after('notes');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->boolean('towing')->default(false)->after('domestic_routing');
        });
    }

    public function down(): void
    {
        Schema::table('prealerts', function (Blueprint $table) {
            $table->dropColumn('towing');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn('towing');
        });
    }
};
