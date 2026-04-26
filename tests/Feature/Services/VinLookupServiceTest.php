<?php

use App\Enums\VinLookupOutcome;
use App\Models\Prealert;
use App\Models\Shipment;
use App\Models\Shipper;
use App\Models\Vehicle;
use App\Services\VinLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns AlreadyOnShipment if vehicle is linked to a shipment', function () {
    $shipper = Shipper::factory()->create();
    $shipment = Shipment::factory()->create(['shipper_id' => $shipper->id]);
    $vehicle = Vehicle::factory()->create([
        'vin' => '12345678901234567',
        'shipment_id' => $shipment->id,
    ]);

    $service = app(VinLookupService::class);
    $result = $service->lookup('12345678901234567', $shipper->id);

    expect($result->outcome)->toBe(VinLookupOutcome::AlreadyOnShipment)
        ->and($result->belongsToAnotherShipper)->toBeFalse()
        ->and($result->message)->toContain('already registered on a shipment');
});

it('returns AlreadyOnPrealert if vehicle is linked to a prealert', function () {
    $shipper = Shipper::factory()->create();
    $prealert = Prealert::factory()->create(['shipper_id' => $shipper->id]);
    $vehicle = Vehicle::factory()->create([
        'vin' => '12345678901234567',
        'prealert_id' => $prealert->id,
    ]);

    $service = app(VinLookupService::class);
    $result = $service->lookup('12345678901234567', $shipper->id);

    expect($result->outcome)->toBe(VinLookupOutcome::AlreadyOnPrealert)
        ->and($result->belongsToAnotherShipper)->toBeFalse()
        ->and($result->message)->toContain('already registered on a pre-alert');
});

it('identifies if the prealert belongs to another shipper', function () {
    $shipper1 = Shipper::factory()->create();
    $shipper2 = Shipper::factory()->create();
    $prealert = Prealert::factory()->create(['shipper_id' => $shipper2->id]);
    $vehicle = Vehicle::factory()->create([
        'vin' => '12345678901234567',
        'prealert_id' => $prealert->id,
    ]);

    $service = app(VinLookupService::class);
    $result = $service->lookup('12345678901234567', $shipper1->id);

    expect($result->outcome)->toBe(VinLookupOutcome::AlreadyOnPrealert)
        ->and($result->belongsToAnotherShipper)->toBeTrue()
        ->and($result->message)->toContain('linked to a pending pre-alert');
});
