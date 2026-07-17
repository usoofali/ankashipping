<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Models\Invoice;
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

test('staff dashboard search overrides month, year, and other filters', function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    Staff::factory()->create(['user_id' => $user->id]);

    // Create a shipment from a previous month/year with unique reference_no
    $shipment = Shipment::factory()->create([
        'reference_no' => 'REF-OVERRIDE-123',
        'created_at' => now()->subMonths(3)->subYears(1),
        'shipment_status' => ShipmentStatus::Delivered,
    ]);

    $this->actingAs($user);

    $component = Volt::test('pages::dashboard.staff');

    // Initially, because mount() sets filterMonth and filterYear to current month/year, the old shipment should not be in results
    expect($component->instance()->shipments->items())->toHaveCount(0);

    // When search is set to the shipment reference, it should override month/year/status filters and return the shipment
    $component->set('search', 'REF-OVERRIDE-123');
    expect($component->instance()->shipments->items())->toHaveCount(1);
    expect($component->instance()->shipments->items()[0]->id)->toBe($shipment->id);
});

test('staff dashboard stats include telex requested count and invoice amounts', function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    Staff::factory()->create(['user_id' => $user->id]);

    Shipment::factory()->create([
        'shipment_status' => ShipmentStatus::TelexRequested,
    ]);

    $shipmentPaid = Shipment::factory()->create([
        'payment_status' => PaymentStatus::Paid,
    ]);
    Invoice::factory()->create([
        'shipment_id' => $shipmentPaid->id,
        'total_amount' => 500.50,
        'status' => InvoiceStatus::Completed,
    ]);

    $this->actingAs($user);

    $component = Volt::test('pages::dashboard.staff');
    $stats = $component->instance()->stats();

    expect($stats['telex_requested'])->toBeGreaterThanOrEqual(1);
    expect((float) $stats['paid_invoices_amount'])->toBeGreaterThanOrEqual(500.50);

    $component->assertSee('Telex Requested');
    $component->assertSee('Received: $500.50');
});
