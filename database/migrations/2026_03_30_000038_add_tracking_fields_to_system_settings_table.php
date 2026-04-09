<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->string('tracking_delivery_prefix')->default('MRF')->after('whatsapp_api_key');
            $table->unsignedSmallInteger('tracking_digits')->default(5)->after('tracking_delivery_prefix');
            $table->string('tracking_number_type')->default('auto_increment')->after('tracking_digits');
            $table->unsignedSmallInteger('tracking_random_digits')->default(10)->after('tracking_number_type');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn([
                'tracking_delivery_prefix',
                'tracking_digits',
                'tracking_number_type',
                'tracking_random_digits',
            ]);
        });
    }
};
