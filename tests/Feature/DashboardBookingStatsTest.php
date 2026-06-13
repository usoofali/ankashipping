<?php

declare(strict_types=1);

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\Staff;
use App\Models\User;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Clear Spatie Permission cache to prevent leak across tests/seeding
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    Cache::forget('spatie.permission.cache');

    Role::findOrCreate('super_admin');
    Role::findOrCreate('staff_admin');
    Role::findOrCreate('staff_operator');

    Permission::findOrCreate('dashboard.view.stats.booking');
    Permission::findOrCreate('whatsapp.view_inbox');

    Role::findByName('super_admin')->givePermissionTo([
        'dashboard.view.stats.booking',
        'whatsapp.view_inbox',
    ]);
});

test('shipment model has booking agent relationship and fillable attribute', function (): void {
    $staff = Staff::factory()->create();
    $shipment = Shipment::factory()->create([
        'shipment_status' => ShipmentStatus::Booking,
        'booking_agent_id' => $staff->id,
    ]);

    expect($shipment->booking_agent_id)->toBe($staff->id);
    expect($shipment->bookingAgent)->toBeInstanceOf(Staff::class);
    expect($shipment->bookingAgent->id)->toBe($staff->id);
});

test('staff dashboard stats include booking, booking unclaimed count, and whatsapp unread messages', function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    Staff::factory()->create(['user_id' => $user->id]);

    $bookingAgent = Staff::factory()->create();

    // Create booking shipments
    Shipment::factory()->create([
        'shipment_status' => ShipmentStatus::Booking,
        'booking_agent_id' => null, // unclaimed
    ]);

    Shipment::factory()->create([
        'shipment_status' => ShipmentStatus::Booking,
        'booking_agent_id' => $bookingAgent->id, // claimed
    ]);

    // Create WhatsApp Conversation and messages
    $conversation = WhatsAppConversation::create([
        'phone_number' => '+1234567890',
        'status' => 'escalated',
    ]);

    // Unread customer messages (should be counted)
    WhatsAppMessage::create([
        'conversation_id' => $conversation->id,
        'sender_type' => 'customer',
        'status' => 'delivered',
        'message_text' => 'Hello 1',
    ]);
    WhatsAppMessage::create([
        'conversation_id' => $conversation->id,
        'sender_type' => 'customer',
        'status' => 'sent',
        'message_text' => 'Hello 2',
    ]);

    // Read customer message (should not be counted)
    WhatsAppMessage::create([
        'conversation_id' => $conversation->id,
        'sender_type' => 'customer',
        'status' => 'read',
        'message_text' => 'Hello 3',
    ]);

    // Agent message (should not be counted)
    WhatsAppMessage::create([
        'conversation_id' => $conversation->id,
        'sender_type' => 'agent',
        'status' => 'delivered',
        'message_text' => 'Reply 1',
    ]);

    $this->actingAs($user);

    $component = Volt::test('pages::dashboard.staff');
    $stats = $component->instance()->stats();

    expect($stats['booking'])->toBe(2);
    expect($stats['booking_unclaimed'])->toBe(1);
    expect($stats['unread_whatsapp'])->toBe(2);

    $component->assertSee('Booking Shipments');
    $component->assertSee('2');
    $component->assertSee('(1 unclaimed)');

    $component->assertSee('WhatsApp');
    $component->assertSee('Unread Messages');
    $component->assertSee('2');
});

test('staff dashboard hides whatsapp card if user lacks permission', function (): void {
    $user = User::factory()->create();
    // Do not assign any roles or permissions, but create a Staff record so they can access the dashboard
    Staff::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $component = Volt::test('pages::dashboard.staff');
    $component->assertDontSee('Unread Messages');
});
