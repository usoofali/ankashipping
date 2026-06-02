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
            $table->string('terminal_name')->nullable();
            $table->string('terminal_state')->nullable();
            $table->string('terminal_zipcode')->nullable();
            $table->text('terminal_address')->nullable();
            $table->string('terminal_phone')->nullable();
            $table->string('terminal_email')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ports', function (Blueprint $table) {
            $table->dropColumn([
                'terminal_name',
                'terminal_state',
                'terminal_zipcode',
                'terminal_address',
                'terminal_phone',
                'terminal_email',
            ]);
        });
    }
};
