<?php

declare(strict_types=1);

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    Cache::forget('spatie.permission.cache');

    Role::findOrCreate('super_admin');
    Role::findOrCreate('staff_admin');
    Role::findOrCreate('staff_operator');

    Permission::findOrCreate('shipments.update');
    Permission::findOrCreate('invoices.edit');
    Permission::findOrCreate('invoices.delete');
    Permission::findOrCreate('invoices.manage');
    Permission::findOrCreate('documents.manage');
    Permission::findOrCreate('documents.delete');
    Permission::findOrCreate('drivers.create');
    Permission::findOrCreate('workflow.manage_logistics');
    Permission::findOrCreate('workflow.assign_driver');

    Role::findByName('super_admin')->givePermissionTo(Permission::all());
});

test('isBookingLockedForUser returns false for non-booking shipments', function (): void {
    $user = User::factory()->create();
    $user->assignRole('staff_operator');
    Staff::factory()->create(['user_id' => $user->id]);

    $shipment = Shipment::factory()->create([
        'shipment_status' => ShipmentStatus::Pending,
        'booking_agent_id' => null,
    ]);

    expect($shipment->isBookingLockedForUser($user))->toBeFalse();
});

test('isBookingLockedForUser returns false for super_admin regardless of claim', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    Staff::factory()->create(['user_id' => $admin->id]);

    $otherStaff = Staff::factory()->create();

    $shipment = Shipment::factory()->create([
        'shipment_status' => ShipmentStatus::Booking,
        'booking_agent_id' => $otherStaff->id,
    ]);

    expect($shipment->isBookingLockedForUser($admin))->toBeFalse();
});

test('isBookingLockedForUser returns true for unclaimed booking', function (): void {
    $user = User::factory()->create();
    $user->assignRole('staff_operator');
    Staff::factory()->create(['user_id' => $user->id]);

    $shipment = Shipment::factory()->create([
        'shipment_status' => ShipmentStatus::Booking,
        'booking_agent_id' => null,
    ]);

    expect($shipment->isBookingLockedForUser($user))->toBeTrue();
});

test('isBookingLockedForUser returns false for the claiming agent', function (): void {
    $user = User::factory()->create();
    $user->assignRole('staff_operator');
    $staff = Staff::factory()->create(['user_id' => $user->id]);

    $shipment = Shipment::factory()->create([
        'shipment_status' => ShipmentStatus::Booking,
        'booking_agent_id' => $staff->id,
    ]);

    expect($shipment->isBookingLockedForUser($user))->toBeFalse();
});

test('isBookingLockedForUser returns true for a different agent', function (): void {
    $user = User::factory()->create();
    $user->assignRole('staff_operator');
    Staff::factory()->create(['user_id' => $user->id]);

    $otherStaff = Staff::factory()->create();

    $shipment = Shipment::factory()->create([
        'shipment_status' => ShipmentStatus::Booking,
        'booking_agent_id' => $otherStaff->id,
    ]);

    expect($shipment->isBookingLockedForUser($user))->toBeTrue();
});

test('claiming a booking sets booking_agent_id atomically', function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $staff = Staff::factory()->create(['user_id' => $user->id]);

    $shipment = Shipment::factory()->create([
        'shipment_status' => ShipmentStatus::Booking,
        'booking_agent_id' => null,
    ]);

    $updated = Shipment::query()
        ->where('id', $shipment->id)
        ->whereNull('booking_agent_id')
        ->update(['booking_agent_id' => $staff->id]);

    expect($updated)->toBe(1);
    expect($shipment->refresh()->booking_agent_id)->toBe($staff->id);
});

test('claiming an already claimed booking fails atomically', function (): void {
    $existingStaff = Staff::factory()->create();

    $shipment = Shipment::factory()->create([
        'shipment_status' => ShipmentStatus::Booking,
        'booking_agent_id' => $existingStaff->id,
    ]);

    $newStaff = Staff::factory()->create();

    $updated = Shipment::query()
        ->where('id', $shipment->id)
        ->whereNull('booking_agent_id')
        ->update(['booking_agent_id' => $newStaff->id]);

    expect($updated)->toBe(0);
    expect($shipment->refresh()->booking_agent_id)->toBe($existingStaff->id);
});

test('admin can release a booking claimed by another agent', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $otherStaff = Staff::factory()->create();

    $shipment = Shipment::factory()->create([
        'shipment_status' => ShipmentStatus::Booking,
        'booking_agent_id' => $otherStaff->id,
    ]);

    $isAdmin = $admin->hasRole(['super_admin', 'staff_admin']);
    expect($isAdmin)->toBeTrue();

    $shipment->update(['booking_agent_id' => null]);
    expect($shipment->refresh()->booking_agent_id)->toBeNull();
});

test('non-admin cannot release booking claimed by another agent', function (): void {
    $user = User::factory()->create();
    $user->assignRole('staff_operator');
    Staff::factory()->create(['user_id' => $user->id]);

    $otherStaff = Staff::factory()->create();

    $shipment = Shipment::factory()->create([
        'shipment_status' => ShipmentStatus::Booking,
        'booking_agent_id' => $otherStaff->id,
    ]);

    $isAdmin = $user->hasRole(['super_admin', 'staff_admin']);
    $isOwner = $user->staff?->id === $shipment->booking_agent_id;

    expect($isAdmin)->toBeFalse();
    expect($isOwner)->toBeFalse();
});
