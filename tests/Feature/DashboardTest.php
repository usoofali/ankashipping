<?php

declare(strict_types=1);

use App\Enums\ShippingMode;
use App\Models\Shipment;
use App\Models\Shipper;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function (): void {
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
});

test('authenticated users can visit the dashboard', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get('/dashboard');
    $response->assertStatus(200);
});

test('staff dashboard header displays title description and live search results by vin or reference', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $shipment = Shipment::factory()->create([
        'reference_no' => 'ANK-98765',
        'shipping_mode' => ShippingMode::Roro,
    ]);

    Vehicle::factory()->create([
        'shipment_id' => $shipment->id,
        'vin' => '1HGCR2F83HA123456',
        'make' => 'Honda',
        'model' => 'Accord',
    ]);

    Volt::test('pages::dashboard.⚡staff')
        ->assertSee('Dashboard')
        ->assertSee('Anka Shipment & Logistics')
        ->set('search', '123456')
        ->assertSee('ANK-98765')
        ->assertSee('Honda Accord');
});

test('shipper dashboard header displays title description and live search results', function (): void {
    $user = User::factory()->create();
    $shipper = Shipper::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $shipment = Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'reference_no' => 'ANK-SHIPPER-001',
        'shipping_mode' => ShippingMode::Roro,
    ]);

    Vehicle::factory()->create([
        'shipment_id' => $shipment->id,
        'vin' => '5NPE24AF8FH999888',
        'make' => 'Hyundai',
        'model' => 'Sonata',
    ]);

    Volt::test('pages::dashboard.⚡shipper')
        ->assertSee('Dashboard')
        ->assertSee('Anka Shipment & Logistics')
        ->set('search', '999888')
        ->assertSee('ANK-SHIPPER-001')
        ->assertSee('Hyundai Sonata');
});

test('both staff and shipper dashboards display paid and due invoices links with filter parameters', function (): void {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    Permission::findOrCreate('dashboard.view.stats.paid_invoices');
    Permission::findOrCreate('dashboard.view.stats.due_invoices');

    // 1. Staff dashboard check
    $staffUser = User::factory()->create();
    $staffUser->givePermissionTo(['dashboard.view.stats.paid_invoices', 'dashboard.view.stats.due_invoices']);
    $this->actingAs($staffUser);

    Volt::test('pages::dashboard.⚡staff')
        ->assertSee(route('shipments.index', ['filterInvoiceState' => 'paid']))
        ->assertSee(route('shipments.index', ['filterInvoiceState' => 'due']));

    // 2. Shipper dashboard check
    Role::findOrCreate('shipper');
    $shipperUser = User::factory()->create();
    $shipperUser->assignRole('shipper');
    Shipper::factory()->create(['user_id' => $shipperUser->id]);
    $this->actingAs($shipperUser);

    Volt::test('pages::dashboard.⚡shipper')
        ->assertSee(route('shipments.index', ['filterInvoiceState' => 'paid']))
        ->assertSee(route('shipments.index', ['filterInvoiceState' => 'due']));
});
