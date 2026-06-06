<?php

declare(strict_types=1);

use App\Enums\ShipmentDocumentType;
use App\Enums\ShipmentStatus;
use App\Enums\ShippingMode;
use App\Enums\VehicleDocumentType;
use App\Enums\VehicleStatus;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;

beforeEach(function (): void {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    Notification::fake();
});

test('container vehicle flow: pending -> dispatched -> inland -> at_warehouse', function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $shipment = Shipment::factory()->create([
        'shipping_mode' => ShippingMode::Container,
        'shipment_status' => ShipmentStatus::Open,
    ]);
    $vehicle = Vehicle::factory()->create([
        'shipment_id' => $shipment->id,
        'tracking_status' => VehicleStatus::Pending,
    ]);
    $driver = Driver::factory()->create();

    $component = Volt::test('pages::shipments.⚡show', ['shipment' => $shipment]);

    // 1. Assign Driver (Pending -> Dispatched)
    $component->set('driver_id', $driver->id)
        ->set('selectedVehicleId', $vehicle->id)
        ->call('assignDriver')
        ->assertHasNoErrors();

    expect($vehicle->refresh()->tracking_status)->toBe(VehicleStatus::Dispatched);

    // 2. Attach Title (Dispatched -> Inland)
    $file = UploadedFile::fake()->create('title.pdf', 100);
    $component->set('attachVehicleDocumentType', VehicleDocumentType::TitleDocument->value)
        ->set('selectedVehicleId', $vehicle->id)
        ->set('attachVehicleFiles', [$file])
        ->call('saveAttachedVehicleDocuments')
        ->assertHasNoErrors();

    expect($vehicle->refresh()->tracking_status)->toBe(VehicleStatus::Inland);

    // 3. Upload Photos (Inland -> AtWarehouse)
    $photo = UploadedFile::fake()->image('car.jpg');
    $component->set('attachVehicleDocumentType', VehicleDocumentType::PhotosAndVideos->value)
        ->set('selectedVehicleId', $vehicle->id)
        ->set('attachVehicleFiles', [$photo])
        ->call('saveAttachedVehicleDocuments')
        ->assertHasNoErrors();

    expect($vehicle->refresh()->tracking_status)->toBe(VehicleStatus::AtWarehouse);
});

test('roro dual sync flow: pending -> dispatched -> inland', function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $shipment = Shipment::factory()->create([
        'shipping_mode' => ShippingMode::Roro,
        'shipment_status' => ShipmentStatus::Pending,
    ]);
    $vehicle = Vehicle::factory()->create([
        'shipment_id' => $shipment->id,
        'tracking_status' => VehicleStatus::Pending,
    ]);
    $driver = Driver::factory()->create();

    $component = Volt::test('pages::shipments.⚡show', ['shipment' => $shipment]);

    // 1. Assign Driver (Both Dispatched)
    $component->set('driver_id', $driver->id)
        ->call('assignDriver')
        ->assertHasNoErrors();

    expect($shipment->refresh()->shipment_status)->toBe(ShipmentStatus::Dispatched);
    expect($vehicle->refresh()->tracking_status)->toBe(VehicleStatus::Dispatched);

    // 2. Attach Title (Both Inland)
    $file = UploadedFile::fake()->create('title.pdf', 100);
    $component->set('attachVehicleDocumentType', VehicleDocumentType::TitleDocument->value)
        ->set('selectedVehicleId', $vehicle->id)
        ->set('attachVehicleFiles', [$file])
        ->call('saveAttachedVehicleDocuments')
        ->assertHasNoErrors();

    expect($shipment->refresh()->shipment_status)->toBe(ShipmentStatus::Booking);
    expect($vehicle->refresh()->tracking_status)->toBe(VehicleStatus::Dispatched);

    // 3. Upload Photos (No change for RoRo)
    $photo = UploadedFile::fake()->image('car.jpg');
    $component->set('attachVehicleDocumentType', VehicleDocumentType::PhotosAndVideos->value)
        ->set('selectedVehicleId', $vehicle->id)
        ->set('attachVehicleFiles', [$photo])
        ->call('saveAttachedVehicleDocuments')
        ->assertHasNoErrors();

    expect($shipment->refresh()->shipment_status)->toBe(ShipmentStatus::Booking);
    expect($vehicle->refresh()->tracking_status)->toBe(VehicleStatus::Dispatched);
});

test('shipment-level flow: inland -> delivered -> loaded -> completed', function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $shipment = Shipment::factory()->create([
        'shipping_mode' => ShippingMode::Container,
        'shipment_status' => ShipmentStatus::Inland,
    ]);

    $component = Volt::test('pages::shipments.⚡show', ['shipment' => $shipment]);

    // 1. Dock Receipt (Inland -> Delivered)
    $doc = UploadedFile::fake()->create('dock.pdf', 100);
    $component->set('attachDocumentType', ShipmentDocumentType::StampDockReceipt->value)
        ->set('attachFiles', [$doc])
        ->call('saveAttachedDocuments')
        ->assertHasNoErrors();

    expect($shipment->refresh()->shipment_status)->toBe(ShipmentStatus::Delivered);

    // 2. Bill of Lading (Delivered -> Loaded)
    $bl = UploadedFile::fake()->create('bl.pdf', 100);
    $component->set('attachDocumentType', ShipmentDocumentType::BillOfLading->value)
        ->set('attachFiles', [$bl])
        ->call('saveAttachedDocuments')
        ->assertHasNoErrors();

    expect($shipment->refresh()->shipment_status)->toBe(ShipmentStatus::Loaded);

    // 3. Complete (Loaded -> Completed)
    $component->call('completeShipment')
        ->assertHasNoErrors();

    expect($shipment->refresh()->shipment_status)->toBe(ShipmentStatus::Completed);
    expect($shipment->isLocked())->toBeTrue();
});

test('cancellation detaches vehicles and locks shipment', function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $shipment = Shipment::factory()->create(['shipment_status' => ShipmentStatus::Open]);
    $vehicle = Vehicle::factory()->create(['shipment_id' => $shipment->id]);

    $shipment->update(['shipment_status' => ShipmentStatus::Cancelled]);

    expect($vehicle->refresh()->shipment_id)->toBeNull();
    expect($shipment->refresh()->shipment_status)->toBe(ShipmentStatus::Cancelled);
    expect($shipment->isLocked())->toBeTrue();
});

test('actions are rejected if status does not match', function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $shipment = Shipment::factory()->create(['shipment_status' => ShipmentStatus::Open]);
    $vehicle = Vehicle::factory()->create(['shipment_id' => $shipment->id, 'tracking_status' => VehicleStatus::Pending]);

    $component = Volt::test('pages::shipments.⚡show', ['shipment' => $shipment]);

    // Try to attach Title when status is PENDING (should be DISPATCHED)
    $file = UploadedFile::fake()->create('title.pdf', 100);
    $component->set('attachVehicleDocumentType', VehicleDocumentType::TitleDocument->value)
        ->set('selectedVehicleId', $vehicle->id)
        ->set('attachVehicleFiles', [$file])
        ->call('saveAttachedVehicleDocuments');

    expect($vehicle->refresh()->tracking_status)->toBe(VehicleStatus::Pending);
});

test('documents view permission is enforced', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $shipment = Shipment::factory()->create();
    $vehicle = Vehicle::factory()->create(['shipment_id' => $shipment->id]);

    // User without permission should be unauthorized when calling openVehicleDocumentsModal
    Volt::test('pages::shipments.⚡show', ['shipment' => $shipment])
        ->call('openVehicleDocumentsModal', $vehicle->id)
        ->assertForbidden();

    // Now assign the permission documents.view
    $user->givePermissionTo('documents.view');

    // User with permission should succeed
    Volt::test('pages::shipments.⚡show', ['shipment' => $shipment])
        ->call('openVehicleDocumentsModal', $vehicle->id)
        ->assertHasNoErrors()
        ->assertSet('selectedVehicleIdForDocs', $vehicle->id)
        ->assertSet('showVehicleDocumentsModal', true);
});
