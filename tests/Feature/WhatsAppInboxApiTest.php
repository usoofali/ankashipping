<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\User;
use App\Modules\WhatsApp\Models\WhatsAppCategory;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatsAppInboxApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $agent;

    protected Staff $staff;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'super_admin']);
        Permission::firstOrCreate(['name' => 'whatsapp.view_inbox']);

        $this->agent = User::factory()->create();
        $this->agent->assignRole('admin');
        $this->agent->givePermissionTo('whatsapp.view_inbox');
        $this->staff = Staff::factory()->create(['user_id' => $this->agent->id]);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super_admin');
        $this->superAdmin->givePermissionTo('whatsapp.view_inbox');
    }

    public function test_can_list_conversations()
    {
        WhatsAppConversation::create(['phone_number' => '1234567890']);
        WhatsAppConversation::create(['phone_number' => '1234567891']);
        WhatsAppConversation::create(['phone_number' => '1234567892']);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/whatsapp/api/conversations');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_can_get_conversation_messages_and_marks_read()
    {
        $conversation = WhatsAppConversation::create(['phone_number' => '1234567890']);
        for ($i = 0; $i < 5; $i++) {
            WhatsAppMessage::create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'customer',
                'status' => 'delivered',
                'message_text' => "Test message {$i}",
            ]);
        }

        $response = $this->actingAs($this->superAdmin)
            ->getJson("/whatsapp/api/conversations/{$conversation->id}/messages");

        $response->assertStatus(200)
            ->assertJsonStructure(['conversation', 'messages']);

        $this->assertCount(5, $response->json('messages'));

        // Assert marked as read happens via separate endpoint usually,
        // but verify it's working if called
        $this->postJson("/whatsapp/api/conversations/{$conversation->id}/read")
            ->assertStatus(200);

        $this->assertDatabaseHas('whatsapp_messages', [
            'conversation_id' => $conversation->id,
            'status' => 'read',
        ]);
    }

    public function test_can_claim_conversation()
    {
        $conversation = WhatsAppConversation::create(['phone_number' => '1234567890', 'agent_id' => null, 'status' => 'bot']);

        $response = $this->actingAs($this->agent)
            ->postJson("/whatsapp/api/conversations/{$conversation->id}/claim");

        $response->assertStatus(200);

        $this->assertDatabaseHas('whatsapp_conversations', [
            'id' => $conversation->id,
            'agent_id' => $this->staff->id,
            'status' => 'escalated',
        ]);
    }

    public function test_can_resolve_conversation()
    {
        $conversation = WhatsAppConversation::create([
            'phone_number' => '1234567890',
            'agent_id' => $this->staff->id,
            'status' => 'escalated',
        ]);

        $response = $this->actingAs($this->agent)
            ->postJson("/whatsapp/api/conversations/{$conversation->id}/resolve");

        $response->assertStatus(200);

        $this->assertDatabaseHas('whatsapp_conversations', [
            'id' => $conversation->id,
            'agent_id' => null,
            'status' => 'bot',
        ]);
    }

    public function test_can_clear_conversation()
    {
        $category = WhatsAppCategory::create(['name' => 'General', 'hashtag' => 'general']);
        $conversation = WhatsAppConversation::create([
            'phone_number' => '1234567890',
            'agent_id' => $this->staff->id,
            'status' => 'escalated',
            'category_id' => $category->id,
        ]);

        for ($i = 0; $i < 5; $i++) {
            WhatsAppMessage::create(['conversation_id' => $conversation->id, 'sender_type' => 'customer', 'message_text' => 'test']);
        }

        $response = $this->actingAs($this->agent)
            ->postJson("/whatsapp/api/conversations/{$conversation->id}/clear");

        $response->assertStatus(200);

        $this->assertDatabaseHas('whatsapp_conversations', [
            'id' => $conversation->id,
            'agent_id' => null,
            'status' => 'bot',
            'category_id' => null,
        ]);

        $this->assertDatabaseMissing('whatsapp_messages', [
            'conversation_id' => $conversation->id,
        ]);
    }
}
