<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
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

test('shipper can filter shipments by due invoice state', function (): void {
    $user = User::factory()->create();
    $user->assignRole('shipper');
    $shipper = Shipper::factory()->create(['user_id' => $user->id]);

    $dueShipment = Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'payment_status' => PaymentStatus::AwaitingPayment,
    ]);
    Invoice::factory()->create([
        'shipment_id' => $dueShipment->id,
        'status' => InvoiceStatus::Completed,
        'updated_at' => now()->subWeeks(4),
    ]);

    $paidShipment = Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'payment_status' => PaymentStatus::Paid,
    ]);
    Invoice::factory()->create([
        'shipment_id' => $paidShipment->id,
        'status' => InvoiceStatus::Completed,
        'updated_at' => now()->subWeeks(4),
    ]);

    $this->actingAs($user);

    $component = Volt::test('pages::shipments.⚡index', ['filterInvoiceState' => 'due']);

    $shipments = $component->instance()->shipments();
    $shipmentIds = collect($shipments->items())->pluck('id');

    expect($shipmentIds)->toContain($dueShipment->id)
        ->not->toContain($paidShipment->id);
});

test('shipper can filter shipments by paid invoice state', function (): void {
    $user = User::factory()->create();
    $user->assignRole('shipper');
    $shipper = Shipper::factory()->create(['user_id' => $user->id]);

    $dueShipment = Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'payment_status' => PaymentStatus::AwaitingPayment,
    ]);
    Invoice::factory()->create([
        'shipment_id' => $dueShipment->id,
        'status' => InvoiceStatus::Completed,
        'updated_at' => now()->subWeeks(4),
    ]);

    $paidShipment = Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'payment_status' => PaymentStatus::Paid,
    ]);
    Invoice::factory()->create([
        'shipment_id' => $paidShipment->id,
        'status' => InvoiceStatus::Completed,
    ]);

    $this->actingAs($user);

    $component = Volt::test('pages::shipments.⚡index', ['filterInvoiceState' => 'paid']);

    $shipments = $component->instance()->shipments();
    $shipmentIds = collect($shipments->items())->pluck('id');

    expect($shipmentIds)->toContain($paidShipment->id)
        ->not->toContain($dueShipment->id);
});

test('staff can filter shipments by due invoice state across multiple shippers', function (): void {
    $adminUser = User::factory()->create();
    $adminUser->assignRole('super_admin');

    $shipper1 = Shipper::factory()->create();
    $shipper2 = Shipper::factory()->create();

    // Due shipment for shipper 1
    $dueShipment1 = Shipment::factory()->create([
        'shipper_id' => $shipper1->id,
        'payment_status' => PaymentStatus::AwaitingPayment,
    ]);
    Invoice::factory()->create([
        'shipment_id' => $dueShipment1->id,
        'status' => InvoiceStatus::Completed,
        'updated_at' => now()->subWeeks(4),
    ]);

    // Due shipment for shipper 2
    $dueShipment2 = Shipment::factory()->create([
        'shipper_id' => $shipper2->id,
        'payment_status' => PaymentStatus::AwaitingPayment,
    ]);
    Invoice::factory()->create([
        'shipment_id' => $dueShipment2->id,
        'status' => InvoiceStatus::Completed,
        'updated_at' => now()->subWeeks(5),
    ]);

    // Paid shipment (not due)
    $paidShipment = Shipment::factory()->create([
        'shipper_id' => $shipper1->id,
        'payment_status' => PaymentStatus::Paid,
    ]);
    Invoice::factory()->create([
        'shipment_id' => $paidShipment->id,
        'status' => InvoiceStatus::Completed,
    ]);

    // Recent invoice (< 3 weeks, not due)
    $recentShipment = Shipment::factory()->create([
        'shipper_id' => $shipper2->id,
        'payment_status' => PaymentStatus::AwaitingPayment,
    ]);
    Invoice::factory()->create([
        'shipment_id' => $recentShipment->id,
        'status' => InvoiceStatus::Completed,
        'updated_at' => now()->subWeeks(1),
    ]);

    $this->actingAs($adminUser);

    $component = Volt::test('pages::shipments.⚡index', ['filterInvoiceState' => 'due']);

    $shipments = $component->instance()->shipments();
    $shipmentIds = collect($shipments->items())->pluck('id');

    expect($shipmentIds)->toContain($dueShipment1->id)
        ->toContain($dueShipment2->id)
        ->not->toContain($paidShipment->id)
        ->not->toContain($recentShipment->id);
    expect($shipments->total())->toBe(2);
});

test('shipper can filter shipments by booked without title', function (): void {
    $user = User::factory()->create();
    $user->assignRole('shipper');
    $shipper = Shipper::factory()->create(['user_id' => $user->id]);

    $otherShipper = Shipper::factory()->create();

    $noTitleShipment = Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'booked_without_title' => true,
    ]);

    $withTitleShipment = Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'booked_without_title' => false,
    ]);

    $otherNoTitleShipment = Shipment::factory()->create([
        'shipper_id' => $otherShipper->id,
        'booked_without_title' => true,
    ]);

    $this->actingAs($user);

    $component = Volt::test('pages::shipments.⚡index', ['filterBookedWithoutTitle' => true]);

    $shipments = $component->instance()->shipments();
    $shipmentIds = collect($shipments->items())->pluck('id');

    expect($shipmentIds)->toContain($noTitleShipment->id)
        ->not->toContain($withTitleShipment->id)
        ->not->toContain($otherNoTitleShipment->id);
});
