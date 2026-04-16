<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vehicle_document_files')) {
            return;
        }

        Schema::create('vehicle_document_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_document_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_document_files');
    }
};
