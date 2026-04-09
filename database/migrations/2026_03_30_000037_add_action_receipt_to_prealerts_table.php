<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('prealerts') || Schema::hasColumn('prealerts', 'auction_receipt')) {
            return;
        }

        Schema::table('prealerts', function (Blueprint $table) {
            $table->string('auction_receipt')->nullable()->after('destination_port_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('prealerts') || ! Schema::hasColumn('prealerts', 'auction_receipt')) {
            return;
        }

        Schema::table('prealerts', function (Blueprint $table) {
            $table->dropColumn('auction_receipt');
        });
    }
};
