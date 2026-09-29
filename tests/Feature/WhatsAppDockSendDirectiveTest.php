<?php

declare(strict_types=1);

use App\Models\ActivityLog;
use App\Models\Shipment;
use App\Models\Staff;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Vehicle;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Services\StaffOperationsService;
use App\Modules\WhatsApp\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'super_admin']);
    Permission::firstOrCreate(['name' => 'whatsapp.view_inbox']);
    Permission::firstOrCreate(['name' => 'workflow.download_dock_receipt']);

    SystemSetting::factory()->create();

    $this->user = User::factory()->create();
    $this->user->assignRole('super_admin');
    $this->user->givePermissionTo('whatsapp.view_inbox');
    $this->user->givePermissionTo('workflow.download_dock_receipt');

    $this->staff = Staff::factory()->create(['user_id' => $this->user->id]);
});

test('staff can send dock receipt via #dock send directive in whatsapp inbox web api', function (): void {
    $shipment = Shipment::factory()->create([
        'reference_no' => 'ANK-DOCK-101',
    ]);

    $conversation = WhatsAppConversation::create([
        'phone_number' => '+15559876543',
        'status' => 'escalated',
        'agent_id' => $this->staff->id,
    ]);

    $this->mock(WhatsAppService::class, function (MockInterface $mock) use ($shipment): void {
        $mock->shouldReceive('sendDocument')
            ->once()
            ->withArgs(function ($phone, $url, $name) use ($shipment) {
                return $phone === '+15559876543'
                    && str_contains($url, 'whatsapp-temp')
                    && str_contains($name, $shipment->reference_no);
            })
            ->andReturn([
                'messages' => [['id' => 'wam_dock_send_123']],
            ]);
    });

    $response = $this->actingAs($this->user)
        ->postJson("/whatsapp/api/conversations/{$conversation->id}/send", [
            'message' => '#dock send ANK-DOCK-101',
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'action' => 'dock_sent',
        ]);

    expect(WhatsAppMessage::where('conversation_id', $conversation->id)->where('message_type', 'document')->exists())->toBeTrue()
        ->and(ActivityLog::where('action', 'whatsapp.send_dock_receipt')->where('shipment_id', $shipment->id)->exists())->toBeTrue();
});

test('staff can send dock receipt using vehicle vin in #dock send directive', function (): void {
    $shipment = Shipment::factory()->create([
        'reference_no' => 'ANK-VIN-202',
    ]);

    $vehicle = Vehicle::factory()->create([
        'shipment_id' => $shipment->id,
        'vin' => '1G1RC6E42BU999999',
    ]);

    $conversation = WhatsAppConversation::create([
        'phone_number' => '+15559876543',
        'status' => 'bot',
    ]);

    $this->mock(WhatsAppService::class, function (MockInterface $mock) use ($shipment): void {
        $mock->shouldReceive('sendDocument')
            ->once()
            ->withArgs(function ($phone, $url, $name) use ($shipment) {
                return $phone === '+15559876543'
                    && str_contains($name, $shipment->reference_no);
            })
            ->andReturn([
                'messages' => [['id' => 'wam_dock_vin_456']],
            ]);
    });

    $response = $this->actingAs($this->user)
        ->postJson("/whatsapp/api/conversations/{$conversation->id}/send", [
            'message' => '#dock send 1G1RC6E42BU999999',
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'action' => 'dock_sent',
        ]);

    $message = WhatsAppMessage::where('conversation_id', $conversation->id)->latest('id')->first();
    expect($message)->not->toBeNull()
        ->and($message->message_type)->toBe('document')
        ->and($message->related_entity_id)->toBe($shipment->id);
});

test('dock send directive returns 422 when reference is missing or invalid', function (): void {
    $conversation = WhatsAppConversation::create([
        'phone_number' => '+15559876543',
        'status' => 'bot',
    ]);

    // Missing reference
    $resMissing = $this->actingAs($this->user)
        ->postJson("/whatsapp/api/conversations/{$conversation->id}/send", [
            'message' => '#dock send',
        ]);

    $resMissing->assertStatus(422)
        ->assertJsonFragment(['success' => false]);

    // Non-existent reference
    $resNotFound = $this->actingAs($this->user)
        ->postJson("/whatsapp/api/conversations/{$conversation->id}/send", [
            'message' => '#dock send NON_EXISTENT_REF_999',
        ]);

    $resNotFound->assertStatus(422)
        ->assertJsonFragment(['success' => false]);
});

test('staff operations service handles #dock send directive from mobile bot conversation', function (): void {
    $shipment = Shipment::factory()->create([
        'reference_no' => 'ANK-MOBILE-303',
    ]);

    $conversation = WhatsAppConversation::create([
        'phone_number' => '+15551112222',
        'contact_type' => Staff::class,
        'contact_id' => $this->staff->id,
        'status' => 'bot',
    ]);

    $this->mock(WhatsAppService::class, function (MockInterface $mock) use ($shipment): void {
        $mock->shouldReceive('sendDocument')
            ->once()
            ->withArgs(function ($phone, $url, $name) use ($shipment) {
                return $phone === '+15551112222'
                    && str_contains($name, $shipment->reference_no);
            })
            ->andReturn([
                'messages' => [['id' => 'wam_mobile_789']],
            ]);

        $mock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($phone, $text) use ($shipment) {
                return $phone === '+15551112222'
                    && str_contains($text, $shipment->reference_no)
                    && str_contains($text, 'sent successfully');
            });
    });

    $staffService = app(StaffOperationsService::class);
    $staffService->processDirective($conversation, '#dock send ANK-MOBILE-303');

    expect(WhatsAppMessage::where('conversation_id', $conversation->id)->where('message_type', 'document')->exists())->toBeTrue();
});
