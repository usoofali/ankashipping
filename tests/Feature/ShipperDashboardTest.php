<?php

declare(strict_types=1);

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\Shipper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('shipper');
});

test('shipper dashboard search overrides month and year filters', function (): void {
    $user = User::factory()->create();
    $user->assignRole('shipper');
    $shipper = Shipper::factory()->create(['user_id' => $user->id]);

    $shipment = Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'reference_no' => 'SHIPPER-OVERRIDE-999',
        'created_at' => now()->subMonths(4)->subYears(1),
        'shipment_status' => ShipmentStatus::Delivered,
    ]);

    $this->actingAs($user);

    $component = Volt::test('pages::dashboard.shipper');

    // Initially, because filterMonth and filterYear are set or default to current month/year, the old shipment is not returned
    $component->set('filterMonth', (string) now()->month);
    $component->set('filterYear', (string) now()->year);
    expect($component->instance()->shipments->items())->toHaveCount(0);

    // When search is set to the shipment reference, it should override month/year filters
    $component->set('search', 'SHIPPER-OVERRIDE-999');
    expect($component->instance()->shipments->items())->toHaveCount(1);
    expect($component->instance()->shipments->items()[0]->id)->toBe($shipment->id);
});

test('shipper dashboard stats include telex requested count and card', function (): void {
    $user = User::factory()->create();
    $user->assignRole('shipper');
    $shipper = Shipper::factory()->create(['user_id' => $user->id]);

    Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'shipment_status' => ShipmentStatus::TelexRequested,
    ]);

    $this->actingAs($user);

    $component = Volt::test('pages::dashboard.shipper');
    $stats = $component->instance()->stats();

    expect($stats['telex_requested'])->toBe(1);

    $component->assertSee('Telex Requested');
});
