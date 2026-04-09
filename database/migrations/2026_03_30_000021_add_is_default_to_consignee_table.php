<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consignees', function (Blueprint $table): void {
            if (! Schema::hasColumn('consignees', 'is_default')) {
                $table->boolean('is_default')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('consignees', function (Blueprint $table): void {
            if (Schema::hasColumn('consignees', 'is_default')) {
                $table->dropColumn('is_default');
            }
        });
    }
};
