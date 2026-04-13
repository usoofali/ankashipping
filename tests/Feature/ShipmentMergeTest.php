<?php

use App\Enums\ShippingMode;
use App\Enums\VehicleStatus;
use App\Models\Prealert;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\ShipmentCreatedNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('super_admin');
});

test('can merge prealert vehicles into existing container shipment', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    // 1. Create target shipment with 2 vehicles
    $shipment = Shipment::factory()->create([
        'shipping_mode' => ShippingMode::Container,
        'capacity' => 5,
    ]);
    Vehicle::factory()->count(2)->create(['shipment_id' => $shipment->id]);

    // 2. Create prealert with 1 vehicle targeting the shipment
    $prealert = Prealert::factory()->create([
        'shipment_id' => $shipment->id,
        'shipping_mode' => ShippingMode::Container,
    ]);
    $newVehicle = Vehicle::factory()->create(['prealert_id' => $prealert->id]);

    // 3. Run the conversion process
    Notification::fake();

    Volt::test('pages::shipments.create', ['prealert' => $prealert->id])
        ->set('initial_vehicle_status', VehicleStatus::Pending->value)
        ->call('save')
        ->assertHasNoErrors();

    // 4. Verify results
    Notification::assertSentTo(
        $user,
        fn (ShipmentCreatedNotification $n) => $n->isMerge === true && $n->addedCount === 1
    );

    expect(Prealert::find($prealert->id))->toBeNull();

    $newVehicle->refresh();
    expect($newVehicle->shipment_id)->toBe($shipment->id);
    expect($shipment->refresh()->vehicles()->count())->toBe(3);
});

test('cannot merge into existing shipment if capacity exceeds 5', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    // 1. Create target shipment with 4 vehicles
    $shipment = Shipment::factory()->create([
        'shipping_mode' => ShippingMode::Container,
        'capacity' => 5,
    ]);
    Vehicle::factory()->count(4)->create(['shipment_id' => $shipment->id]);

    // 2. Create prealert with 2 vehicles targeting the shipment (Total 6)
    $prealert = Prealert::factory()->create([
        'shipment_id' => $shipment->id,
        'shipping_mode' => ShippingMode::Container,
    ]);
    Vehicle::factory()->count(2)->create(['prealert_id' => $prealert->id]);

    // 3. Run the conversion process and expect error
    Volt::test('pages::shipments.create', ['prealert' => $prealert->id])
        ->set('initial_vehicle_status', VehicleStatus::Pending->value)
        ->call('save')
        ->assertHasNoErrors() // No validation errors, but...
        ->assertStatus(200);

    // Verify the vehicle was NOT merged and prealert NOT deleted
    expect($shipment->refresh()->vehicles()->count())->toBe(4);
    expect(Prealert::find($prealert->id))->not->toBeNull();
});
