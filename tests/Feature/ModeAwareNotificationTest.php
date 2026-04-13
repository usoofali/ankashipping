<?php

use App\Enums\ShippingMode;
use App\Models\Prealert;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\PrealertCreatedNotification;
use App\Notifications\ShipmentCreatedNotification;

test('ShipmentCreatedNotification content is mode aware', function () {
    $user = User::factory()->create();
    $shipment = Shipment::factory()->create(['shipping_mode' => ShippingMode::Roro]);
    $vehicle = Vehicle::factory()->create(['shipment_id' => $shipment->id, 'vin' => 'VIN-RORO']);

    // RoRo Mode
    $notification = new ShipmentCreatedNotification($shipment);
    $data = $notification->toArray($user);

    expect($data['title'])->toBe('New RoRo Shipment');
    expect($data['body'])->toContain('VIN-RORO');

    // Container Mode
    $shipment->update(['shipping_mode' => ShippingMode::Container]);
    Vehicle::factory()->count(2)->create(['shipment_id' => $shipment->id]);

    $notification = new ShipmentCreatedNotification($shipment->fresh());
    $data = $notification->toArray($user);

    expect($data['title'])->toBe('New Container Shipment');
    expect($data['body'])->toContain('3 vehicles'); // 1 original + 2 new
});

test('PrealertCreatedNotification content is mode aware', function () {
    $user = User::factory()->create();
    $prealert = Prealert::factory()->create(['shipping_mode' => ShippingMode::Roro]);
    $vehicle = Vehicle::factory()->create(['prealert_id' => $prealert->id, 'vin' => 'VIN-PRE-RORO']);

    // RoRo Mode
    $notification = new PrealertCreatedNotification($prealert);
    $data = $notification->toArray($user);

    expect($data['title'])->toBe('New RoRo Prealert');
    expect($data['body'])->toContain('VIN-PRE-RORO');
    expect($data['vin'])->toBe('VIN-PRE-RORO');

    // Container Mode
    $prealert->update(['shipping_mode' => ShippingMode::Container]);
    Vehicle::factory()->count(2)->create(['prealert_id' => $prealert->id]);

    $notification = new PrealertCreatedNotification($prealert->fresh());
    $data = $notification->toArray($user);

    expect($data['title'])->toBe('New Container Prealert');
    expect($data['body'])->toContain('3 vehicles');
    expect($data['vin'])->toBeNull();
});
