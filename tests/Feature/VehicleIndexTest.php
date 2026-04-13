<?php

use App\Models\Shipment;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $role = Role::findOrCreate('super_admin');
    $this->user = User::factory()->create();
    $this->user->assignRole($role);
});

test('only shows vehicles without shipment or prealert', function () {
    $this->actingAs($this->user);

    Vehicle::factory()->create(['vin' => 'HIDDEN123', 'shipment_id' => Shipment::factory()->create()->id]);
    Vehicle::factory()->create(['vin' => 'VISIBLE789', 'shipment_id' => null, 'prealert_id' => null]);

    Volt::test('pages::vehicles.index')
        ->assertSee('VISIBLE789')
        ->assertDontSee('HIDDEN123');
});

test('can search vehicles', function () {
    $this->actingAs($this->user);

    $v1 = Vehicle::factory()->create(['vin' => 'UNIQUEVIN123', 'make' => 'Toyota']);
    $v2 = Vehicle::factory()->create(['vin' => 'OTHERVIN456', 'make' => 'Honda']);

    Volt::test('pages::vehicles.index')
        ->set('search', 'Toyota')
        ->assertSee('UNIQUEVIN123')
        ->assertDontSee('OTHERVIN456');
});

test('can create a vehicle manually', function () {
    $this->actingAs($this->user);

    Volt::test('pages::vehicles.index')
        ->set('vin', 'MANUALVIN789')
        ->set('make', 'Ford')
        ->set('year', '2023')
        ->call('saveNewVehicle')
        ->assertHasNoErrors();

    $vehicle = Vehicle::where('vin', 'MANUALVIN789')->first();
    expect($vehicle)->not->toBeNull()
        ->and($vehicle->make)->toBe('Ford')
        ->and($vehicle->api_snapshot['provider'])->toBe('manual-entry');
});

test('can edit a vehicle', function () {
    $this->actingAs($this->user);

    $vehicle = Vehicle::factory()->create(['vin' => 'EDITME123', 'make' => 'Original']);

    Volt::test('pages::vehicles.index')
        ->call('openEditModal', $vehicle->id)
        ->set('make', 'Updated')
        ->call('saveVehicle')
        ->assertHasNoErrors();

    expect($vehicle->refresh()->make)->toBe('Updated');
});

test('can delete a vehicle', function () {
    $this->actingAs($this->user);

    $vehicle = Vehicle::factory()->create(['vin' => 'DELETEME123']);

    Volt::test('pages::vehicles.index')
        ->call('openDeleteModal', $vehicle->id)
        ->call('deleteVehicle')
        ->assertHasNoErrors();

    expect(Vehicle::find($vehicle->id))->toBeNull();
});

test('cannot delete vehicle linked to shipment', function () {
    $this->actingAs($this->user);

    $shipment = Shipment::factory()->create();
    $vehicle = Vehicle::factory()->create(['shipment_id' => $shipment->id]);

    Volt::test('pages::vehicles.index')
        ->call('openDeleteModal', $vehicle->id)
        ->call('deleteVehicle');

    expect(Vehicle::find($vehicle->id))->not->toBeNull();
});
