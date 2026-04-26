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
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->string('sender_type'); // customer, bot, agent
            $table->text('message_text')->nullable();
            $table->string('message_type')->default('text'); // text, image, document
            $table->string('media_url')->nullable();
            $table->string('whatsapp_message_id')->nullable()->unique();
            $table->string('status')->default('sent'); // pending, sent, delivered, read, failed
            $table->nullableMorphs('related_entity'); // To deduplicate status updates (Shipment, etc)
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
