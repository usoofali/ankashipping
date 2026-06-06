<?php

declare(strict_types=1);

use App\Models\Shipment;
use App\Models\Shipper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('super_admin');
    Role::findOrCreate('shipper');
});

test('admin can see all shipments and the shipper filter', function (): void {
    $adminUser = User::factory()->create();
    $adminUser->assignRole('super_admin');

    $shipper1 = Shipper::factory()->create();
    $shipper2 = Shipper::factory()->create();

    $shipment1 = Shipment::factory()->create(['shipper_id' => $shipper1->id]);
    $shipment2 = Shipment::factory()->create(['shipper_id' => $shipper2->id]);

    $this->actingAs($adminUser);

    $component = Volt::test('pages::shipments.⚡index');

    $shipments = $component->instance()->shipments();
    $shipmentIds = collect($shipments->items())->pluck('id');
    expect($shipmentIds)->toContain($shipment1->id)
        ->toContain($shipment2->id);

    $shippers = $component->instance()->shippers();
    expect($shippers->pluck('id'))->toContain($shipper1->id)
        ->toContain($shipper2->id);
});

test('shipper can only see their own shipments and not the shipper filter list', function (): void {
    $user1 = User::factory()->create();
    $user1->assignRole('shipper');
    $shipper1 = Shipper::factory()->create(['user_id' => $user1->id]);

    $user2 = User::factory()->create();
    $user2->assignRole('shipper');
    $shipper2 = Shipper::factory()->create(['user_id' => $user2->id]);

    $shipment1 = Shipment::factory()->create(['shipper_id' => $shipper1->id]);
    $shipment2 = Shipment::factory()->create(['shipper_id' => $shipper2->id]);

    $this->actingAs($user1);

    $component = Volt::test('pages::shipments.⚡index');

    $shipments = $component->instance()->shipments();
    $shipmentIds = collect($shipments->items())->pluck('id');
    expect($shipmentIds)->toContain($shipment1->id)
        ->not->toContain($shipment2->id);

    $shippers = $component->instance()->shippers();
    expect($shippers)->toBeEmpty();
});
