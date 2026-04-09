<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charge_items', function (Blueprint $table) {
            $table->decimal('default_amount', 14, 2)->default(0)->after('description');
            $table->boolean('apply_customer_discount')->default(false)->after('default_amount');
        });

        Schema::table('shippers', function (Blueprint $table) {
            $table->decimal('discount_amount', 14, 2)->default(0)->after('city_id');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('gross_amount', 14, 2)->nullable()->after('description');
            $table->decimal('discount_amount', 14, 2)->default(0)->after('gross_amount');
        });

        DB::table('invoice_items')->update([
            'gross_amount' => DB::raw('amount'),
            'discount_amount' => 0,
        ]);
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['gross_amount', 'discount_amount']);
        });

        Schema::table('shippers', function (Blueprint $table) {
            $table->dropColumn('discount_amount');
        });

        Schema::table('charge_items', function (Blueprint $table) {
            $table->dropColumn(['default_amount', 'apply_customer_discount']);
        });
    }
};
