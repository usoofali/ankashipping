<?php

use App\Enums\InvoiceStatus;
use App\Enums\LogisticsService;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Enums\ShippingMode;
use App\Enums\VehicleStatus;
use App\Models\Carrier;
use App\Models\Consignee;
use App\Models\PaymentMethod;
use App\Models\Port;
use App\Models\Prealert;
use App\Models\Shipper;
use App\Models\User;
use App\Models\Vehicle;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Create roles and permissions
    Role::findOrCreate('super_admin');
});

test('prealert_id is nullified on vehicles when shipment is created from prealert', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $shipper = Shipper::factory()->create();
    $consignee = Consignee::factory()->create(['shipper_id' => $shipper->id]);
    $carrier = Carrier::factory()->create();
    $originPort = Port::factory()->create(['type' => 'origin']);
    $destPort = Port::factory()->create(['type' => 'destination']);
    $paymentMethod = PaymentMethod::factory()->create();

    $prealert = Prealert::factory()->create([
        'shipper_id' => $shipper->id,
        'consignee_id' => $consignee->id,
        'carrier_id' => $carrier->id,
        'destination_port_id' => $destPort->id,
        'shipping_mode' => ShippingMode::Roro,
    ]);

    $vehicle = Vehicle::factory()->create([
        'prealert_id' => $prealert->id,
        'vin' => 'TESTVIN1234567890',
    ]);

    // Verify initial state
    expect($vehicle->prealert_id)->toBe($prealert->id);

    // Run the component
    Volt::test('pages::shipments.create', ['prealert' => $prealert->id])
        ->set('origin_port_id', $originPort->id)
        ->set('logistics_service', LogisticsService::Ocean->value)
        ->set('shipment_status', ShipmentStatus::Pending->value)
        ->set('invoice_status', InvoiceStatus::Draft->value)
        ->set('payment_status', PaymentStatus::AwaitingBL->value)
        ->set('payment_method_id', $paymentMethod->id)
        ->set('initial_vehicle_status', VehicleStatus::Pending->value)
        ->call('save')
        ->assertHasNoErrors();

    // Verify prealert is deleted
    expect(Prealert::find($prealert->id))->toBeNull();

    // Verify vehicle's prealert_id is null and shipment_id is set
    $vehicle->refresh();
    expect($vehicle->prealert_id)->toBeNull();
    expect($vehicle->shipment_id)->not->toBeNull();
});

test('single vehicle can be converted to container shipment', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $shipper = Shipper::factory()->create();
    $consignee = Consignee::factory()->create(['shipper_id' => $shipper->id]);
    $originPort = Port::factory()->create(['type' => 'origin']);
    $destPort = Port::factory()->create(['type' => 'destination']);

    $prealert = Prealert::factory()->create([
        'shipper_id' => $shipper->id,
        'consignee_id' => $consignee->id,
        'shipping_mode' => ShippingMode::Roro, // Originally RoRo
    ]);

    $vehicle = Vehicle::factory()->create(['prealert_id' => $prealert->id]);

    // Convert to shipment but switch mode to Container
    Volt::test('pages::shipments.create', ['prealert' => $prealert->id])
        ->set('origin_port_id', $originPort->id)
        ->set('shipping_mode', ShippingMode::Container->value) // Switch to Container
        ->set('logistics_service', LogisticsService::Ocean->value)
        ->set('shipment_status', ShipmentStatus::Pending->value)
        ->set('invoice_status', InvoiceStatus::Draft->value)
        ->set('payment_status', PaymentStatus::AwaitingBL->value)
        ->set('initial_vehicle_status', VehicleStatus::Pending->value)
        ->set('capacity', 5)
        ->call('save')
        ->assertHasNoErrors();

    // Verify shipment was created as Container
    $shipment = $vehicle->refresh()->shipment;
    expect($shipment->shipping_mode)->toBe(ShippingMode::Container);
    expect($shipment->capacity)->toBe(5);
});
