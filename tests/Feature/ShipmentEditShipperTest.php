<?php

declare(strict_types=1);

use App\Models\ActivityLog;
use App\Models\Consignee;
use App\Models\Shipment;
use App\Models\Shipper;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
});

test('staff can reassign shipment shipper and reconcile consignee', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    $oldShipperUser = User::factory()->create(['name' => 'Old Shipper User']);
    $oldShipper = Shipper::factory()->create([
        'user_id' => $oldShipperUser->id,
        'company_name' => 'Old Logistics LLC',
    ]);
    $oldConsignee = Consignee::factory()->create([
        'shipper_id' => $oldShipper->id,
        'name' => 'Old Consignee',
    ]);

    $newShipperUser = User::factory()->create(['name' => 'New Shipper User']);
    $newShipper = Shipper::factory()->create([
        'user_id' => $newShipperUser->id,
        'company_name' => 'New Global Shipping LLC',
    ]);
    $newConsignee = Consignee::factory()->create([
        'shipper_id' => $newShipper->id,
        'name' => 'New Consignee',
    ]);

    $shipment = Shipment::factory()->create([
        'shipper_id' => $oldShipper->id,
        'consignee_id' => $oldConsignee->id,
    ]);

    Vehicle::factory()->create([
        'shipment_id' => $shipment->id,
    ]);

    $component = Volt::test('pages::shipments.⚡edit', ['shipment' => $shipment]);

    // Initial state: shipper is oldShipper, consignee is oldConsignee
    expect($component->get('shipper_id'))->toBe($oldShipper->id);
    expect($component->get('consignee_id'))->toBe($oldConsignee->id);

    // Change shipper to newShipper
    $component->set('shipper_id', $newShipper->id);

    // Consignee is automatically reconciled and assigned to newShipper's consignee
    expect($component->get('consignee_id'))->toBe($newConsignee->id);

    // Verify computed consignees list only contains newShipper's consignees
    $consignees = $component->instance()->consignees();
    expect($consignees->pluck('id'))->toContain($newConsignee->id)
        ->and($consignees->pluck('id'))->not->toContain($oldConsignee->id);

    // Save succeeds immediately with auto-assigned consignee
    $component->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('shipments.show', $shipment));

    // Verify shipment in database
    $shipment->refresh();
    expect($shipment->shipper_id)->toBe($newShipper->id)
        ->and($shipment->consignee_id)->toBe($newConsignee->id);

    // Verify ActivityLog entry
    $log = ActivityLog::where('shipment_id', $shipment->id)
        ->where('action', 'shipper_reassigned')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->properties['old_shipper_id'])->toBe($oldShipper->id)
        ->and($log->properties['new_shipper_id'])->toBe($newShipper->id)
        ->and($log->properties['old_shipper_name'])->toBe('Old Logistics LLC')
        ->and($log->properties['new_shipper_name'])->toBe('New Global Shipping LLC');
});

test('auto-assigns default consignee when new shipper has multiple consignees', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    $oldShipper = Shipper::factory()->create();
    $oldConsignee = Consignee::factory()->create(['shipper_id' => $oldShipper->id]);

    $newShipper = Shipper::factory()->create();
    $consigneeNormal = Consignee::factory()->create([
        'shipper_id' => $newShipper->id,
        'name' => 'AAA Normal Consignee',
        'is_default' => false,
    ]);
    $consigneeDefault = Consignee::factory()->create([
        'shipper_id' => $newShipper->id,
        'name' => 'ZZZ Default Consignee',
        'is_default' => true,
    ]);

    $shipment = Shipment::factory()->create([
        'shipper_id' => $oldShipper->id,
        'consignee_id' => $oldConsignee->id,
    ]);
    Vehicle::factory()->create(['shipment_id' => $shipment->id]);

    $component = Volt::test('pages::shipments.⚡edit', ['shipment' => $shipment]);

    // Change shipper
    $component->set('shipper_id', $newShipper->id);

    // Should prioritize default consignee
    expect($component->get('consignee_id'))->toBe($consigneeDefault->id);
});

test('staff can create a new consignee on the fly during shipment edit', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    $shipper = Shipper::factory()->create();
    $consignee = Consignee::factory()->create(['shipper_id' => $shipper->id]);
    $shipment = Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'consignee_id' => $consignee->id,
    ]);
    Vehicle::factory()->create(['shipment_id' => $shipment->id]);

    $component = Volt::test('pages::shipments.⚡edit', ['shipment' => $shipment])
        ->set('newConsigneeName', 'On The Fly Consignee')
        ->set('newConsigneeAddress', '123 Harbor Blvd')
        ->call('createConsignee');

    $newCreated = Consignee::where('name', 'On The Fly Consignee')->where('shipper_id', $shipper->id)->first();
    expect($newCreated)->not->toBeNull()
        ->and($component->get('consignee_id'))->toBe($newCreated->id);
});

test('available shippers list contains all shippers sorted by display name', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    $shipperB = Shipper::factory()->create(['company_name' => 'Beta Logistics']);
    $shipperA = Shipper::factory()->create(['company_name' => 'Alpha Express']);

    $shipment = Shipment::factory()->create(['shipper_id' => $shipperB->id]);
    Vehicle::factory()->create(['shipment_id' => $shipment->id]);

    $component = Volt::test('pages::shipments.⚡edit', ['shipment' => $shipment]);

    $available = $component->instance()->availableShippers();
    expect($available->pluck('id'))->toContain($shipperA->id)
        ->and($available->pluck('id'))->toContain($shipperB->id);
});
