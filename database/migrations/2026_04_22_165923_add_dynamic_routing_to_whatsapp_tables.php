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
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('agent_id')->constrained('whatsapp_categories')->nullOnDelete();
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('conversation_id')->constrained('whatsapp_categories')->nullOnDelete();
            $table->string('decorator')->nullable()->after('message_text');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn(['category_id', 'decorator']);
        });
    }
};
