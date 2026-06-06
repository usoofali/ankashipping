<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_user_stats', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number')->unique();
            $table->nullableMorphs('contact'); // contact_id, contact_type
            $table->string('contact_name')->nullable();
            $table->string('contact_role')->nullable(); // shipper, staff, driver, unknown
            $table->unsignedBigInteger('total_messages')->default(0);
            $table->unsignedBigInteger('conversation_count')->default(0);
            $table->timestamp('first_contact_at')->nullable();
            $table->timestamp('last_contact_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_user_stats');
    }
};
