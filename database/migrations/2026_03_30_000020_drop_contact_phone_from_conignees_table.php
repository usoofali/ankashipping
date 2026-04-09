<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consignees', function (Blueprint $table): void {
            if (! Schema::hasTable('consignees')) {
                return;
            }

            if (Schema::hasColumn('consignees', 'contact')) {
                $table->dropColumn('contact');
            }

            if (Schema::hasColumn('consignees', 'phone')) {
                $table->dropColumn('phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('consignees', function (Blueprint $table): void {
            if (! Schema::hasTable('consignees')) {
                return;
            }

            if (! Schema::hasColumn('consignees', 'contact')) {
                $table->string('contact')->nullable();
            }

            if (! Schema::hasColumn('consignees', 'phone')) {
                $table->string('phone')->nullable();
            }
        });
    }
};
