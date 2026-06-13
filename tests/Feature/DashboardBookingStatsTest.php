<?php

declare(strict_types=1);

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\Shipper;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('super_admin');
    Role::findOrCreate('shipper');
    Role::findOrCreate('staff_admin');
    Role::findOrCreate('staff_operator');

    Permission::findOrCreate('dashboard.view.stats.booking');
    Role::findByName('super_admin')->givePermissionTo('dashboard.view.stats.booking');
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

test('shipper dashboard stats include booking and booking unclaimed count', function (): void {
    $user = User::factory()->create();
    $user->assignRole('shipper');
    $shipper = Shipper::factory()->create(['user_id' => $user->id]);

    $staff = Staff::factory()->create();

    // Create booking shipments for this shipper
    Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'shipment_status' => ShipmentStatus::Booking,
        'booking_agent_id' => null, // unclaimed
    ]);

    Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'shipment_status' => ShipmentStatus::Booking,
        'booking_agent_id' => $staff->id, // claimed
    ]);

    // Create a non-booking shipment
    Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'shipment_status' => ShipmentStatus::Loaded,
    ]);

    // Create booking shipment for another shipper
    $otherShipper = Shipper::factory()->create();
    Shipment::factory()->create([
        'shipper_id' => $otherShipper->id,
        'shipment_status' => ShipmentStatus::Booking,
        'booking_agent_id' => null,
    ]);

    $this->actingAs($user);

    $component = Volt::test('pages::dashboard.shipper');
    $stats = $component->instance()->stats();

    expect($stats['booking'])->toBe(2);
    expect($stats['booking_unclaimed'])->toBe(1);

    $component->assertSee('Booking Shipments');
    $component->assertSee('2');
    $component->assertSee('(1 unclaimed)');
});

test('staff dashboard stats include booking and booking unclaimed count', function (): void {
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

    $this->actingAs($user);

    $component = Volt::test('pages::dashboard.staff');
    $stats = $component->instance()->stats();

    expect($stats['booking'])->toBe(2);
    expect($stats['booking_unclaimed'])->toBe(1);

    $component->assertSee('Booking Shipments');
    $component->assertSee('2');
    $component->assertSee('(1 unclaimed)');
});
