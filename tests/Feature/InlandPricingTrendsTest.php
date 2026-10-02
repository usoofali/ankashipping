<?php

declare(strict_types=1);

use App\Models\InlandRouteRate;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Port;
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

test('sync command aggregates historical inland invoice items into inland_route_rates', function (): void {
    $port = Port::factory()->create(['name' => 'Port of Baltimore']);
    $shipper = Shipper::factory()->create();

    // Create 2 shipments on the same route with different inland amounts
    $shipment1 = Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'origin_port_id' => $port->id,
    ]);
    Vehicle::factory()->create([
        'shipment_id' => $shipment1->id,
        'location' => 'tx - dallas',
    ]);
    $invoice1 = Invoice::factory()->create([
        'shipment_id' => $shipment1->id,
        'created_at' => now()->subDays(5),
    ]);
    InvoiceItem::factory()->create([
        'invoice_id' => $invoice1->id,
        'description' => 'Inland Towing',
        'amount' => 400.00,
    ]);

    $shipment2 = Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'origin_port_id' => $port->id,
    ]);
    Vehicle::factory()->create([
        'shipment_id' => $shipment2->id,
        'location' => 'tx - dallas',
    ]);
    $invoice2 = Invoice::factory()->create([
        'shipment_id' => $shipment2->id,
        'created_at' => now()->subDay(),
    ]);
    InvoiceItem::factory()->create([
        'invoice_id' => $invoice2->id,
        'description' => 'Inland',
        'amount' => 500.00,
    ]);

    $exitCode = $this->artisan('inland:sync-rates')->run();
    expect($exitCode)->toBe(0);

    $routeRate = InlandRouteRate::where('pickup_location', 'tx - dallas')
        ->where('origin_port_id', $port->id)
        ->first();

    expect($routeRate)->not->toBeNull()
        ->and((float) $routeRate->latest_rate)->toBe(500.00)
        ->and((float) $routeRate->average_rate)->toBe(450.00)
        ->and((float) $routeRate->min_rate)->toBe(400.00)
        ->and((float) $routeRate->max_rate)->toBe(500.00)
        ->and($routeRate->shipment_count)->toBe(2);
});

test('inland rates page is accessible and provides interactive estimator lookup', function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $port = Port::factory()->create(['name' => 'Newark']);
    $rate = InlandRouteRate::create([
        'pickup_location' => 'il - chicago north',
        'origin_port_id' => $port->id,
        'latest_rate' => 600.00,
        'average_rate' => 550.00,
        'min_rate' => 500.00,
        'max_rate' => 650.00,
        'shipment_count' => 12,
        'last_shipped_at' => now(),
    ]);

    $response = $this->get(route('inland-rates.index'));
    $response->assertOk();
});

test('interactive rate estimator provides benchmark and trend comparison', function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $port = Port::factory()->create(['name' => 'Newark']);
    InlandRouteRate::create([
        'pickup_location' => 'il - chicago north',
        'origin_port_id' => $port->id,
        'latest_rate' => 600.00,
        'average_rate' => 550.00,
        'min_rate' => 500.00,
        'max_rate' => 650.00,
        'shipment_count' => 12,
        'last_shipped_at' => now(),
    ]);

    $component = Volt::test('pages::inland-rates.index')
        ->set('estimatorLocation', 'il - chicago north')
        ->set('estimatorPortId', $port->id);

    $estimated = $component->instance()->estimatedRate();
    expect($estimated)->not->toBeNull()
        ->and((float) $estimated->latest_rate)->toBe(600.00)
        ->and((float) $estimated->average_rate)->toBe(550.00)
        ->and($estimated->trend_percentage)->toBe(9.1);
});

test('inland rates table supports searching and route selection', function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $portA = Port::factory()->create(['name' => 'Savannah']);
    $portB = Port::factory()->create(['name' => 'Houston']);

    $rateA = InlandRouteRate::create([
        'pickup_location' => 'ga - atlanta',
        'origin_port_id' => $portA->id,
        'latest_rate' => 350.00,
        'average_rate' => 350.00,
        'min_rate' => 350.00,
        'max_rate' => 350.00,
        'shipment_count' => 5,
    ]);

    $rateB = InlandRouteRate::create([
        'pickup_location' => 'tx - dallas',
        'origin_port_id' => $portB->id,
        'latest_rate' => 275.00,
        'average_rate' => 275.00,
        'min_rate' => 275.00,
        'max_rate' => 275.00,
        'shipment_count' => 8,
    ]);

    $component = Volt::test('pages::inland-rates.index');

    // Click select route
    $component->call('selectRoute', 'tx - dallas', $portB->id);
    expect($component->get('estimatorLocation'))->toBe('tx - dallas')
        ->and($component->get('estimatorPortId'))->toBe($portB->id);

    // Search filter
    $component->set('search', 'atlanta');
    $rates = $component->instance()->rates();
    expect($rates->pluck('id'))->toContain($rateA->id)
        ->and($rates->pluck('id'))->not->toContain($rateB->id);
});

test('location acronyms like LA and IL are formatted in all caps in the view', function (): void {
    expect(InlandRouteRate::formatLocation('la - new orleans'))->toBe('LA - New Orleans')
        ->and(InlandRouteRate::formatLocation('il - chicago north'))->toBe('IL - Chicago North')
        ->and(InlandRouteRate::formatLocation('tx - houston'))->toBe('TX - Houston')
        ->and(InlandRouteRate::formatLocation('sc - north charleston'))->toBe('SC - North Charleston')
        ->and(InlandRouteRate::formatLocation('dc - washington dc'))->toBe('DC - Washington DC')
        ->and(InlandRouteRate::formatLocation('tn - knoxville'))->toBe('TN - Knoxville');

    $port = Port::factory()->create();
    $rate = InlandRouteRate::create([
        'pickup_location' => 'la - new orleans',
        'origin_port_id' => $port->id,
        'latest_rate' => 450.00,
        'average_rate' => 450.00,
        'min_rate' => 450.00,
        'max_rate' => 450.00,
        'shipment_count' => 3,
    ]);

    // Database stores canonical lowercase
    expect($rate->pickup_location)->toBe('la - new orleans')
        // View presentation accessor returns ALL CAPS acronym
        ->and($rate->formatted_location)->toBe('LA - New Orleans');
});

test('inland rates page renders with flux table and pagination when records exceed per-page limit', function (): void {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $port = Port::factory()->create(['name' => 'Houston']);

    // Create 18 rate records (page size is 15)
    for ($i = 1; $i <= 18; $i++) {
        InlandRouteRate::create([
            'pickup_location' => "tx - city {$i}",
            'origin_port_id' => $port->id,
            'latest_rate' => 200.00 + $i * 10,
            'average_rate' => 200.00 + $i * 10,
            'min_rate' => 200.00,
            'max_rate' => 400.00,
            'shipment_count' => $i,
        ]);
    }

    $response = $this->get(route('inland-rates.index'));
    $response->assertOk();

    $component = Volt::test('pages::inland-rates.index');
    expect($component->instance()->rates()->total())->toBe(18)
        ->and($component->instance()->rates()->hasPages())->toBeTrue()
        ->and($component->instance()->rates()->lastPage())->toBe(2);

    $component->assertSee('data-flux-table', escape: false);
});

test('shipper view hides manager kpi metrics and sync action while rendering rates flux table and estimator', function (): void {
    $user = User::factory()->create();
    $user->assignRole('shipper');
    $this->actingAs($user);

    $port = Port::factory()->create(['name' => 'Savannah']);
    InlandRouteRate::create([
        'pickup_location' => 'ga - savannah local',
        'origin_port_id' => $port->id,
        'latest_rate' => 250.00,
        'average_rate' => 250.00,
        'min_rate' => 250.00,
        'max_rate' => 250.00,
        'shipment_count' => 10,
    ]);

    $response = $this->get(route('inland-rates.index'));
    $response->assertOk();

    // Staff actions/KPIs are hidden for shippers
    $response->assertDontSee('Sync Rates from Invoices');
    $response->assertDontSee('Monitored Routes');
    $response->assertDontSee('Network Average');

    // Customer-facing estimator & table are visible
    $response->assertSee('Instant Rate Estimator');
    $response->assertSee('GA - Savannah Local');
    $response->assertSee('Calculate');
});
