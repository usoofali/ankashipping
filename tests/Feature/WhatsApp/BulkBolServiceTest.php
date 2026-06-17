<?php

use App\Enums\ShipmentDocumentType;
use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Vehicle;
use App\Modules\WhatsApp\Services\BulkBolService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;

it('extracts VIN correctly from text', function () {
    $service = app(BulkBolService::class);

    $method = new ReflectionMethod(BulkBolService::class, 'extractVin');
    $method->setAccessible(true);

    $text = "CHASSIS NUMBER(S) USED UNPACKED VEHICLE(S)\nHS Code: 8703900100\n1HGCR2F3XDA135424 1 Honda Accord Model Year:2013 1,310 11.08";
    $vin = $method->invoke($service, $text);

    expect($vin)->toBe('1HGCR2F3XDA135424');
});

it('returns null if VIN is missing or invalid format', function () {
    $service = app(BulkBolService::class);

    $method = new ReflectionMethod(BulkBolService::class, 'extractVin');
    $method->setAccessible(true);

    $text = "CHASSIS NUMBER(S) USED UNPACKED VEHICLE(S)\nHS Code: 8703900100\nSHORTVIN 1 Honda Accord Model Year:2013 1,310 11.08";
    $vin = $method->invoke($service, $text);

    expect($vin)->toBeNull();
});

it('processes a matched page correctly', function () {
    Notification::fake();
    $user = User::factory()->create();
    $shipment = Shipment::factory()->create([
        'shipment_status' => ShipmentStatus::Delivered,
        'booked_without_title' => true,
    ]);
    $vehicle = Vehicle::factory()->create([
        'shipment_id' => $shipment->id,
        'vin' => '1HGCR2F3XDA135424',
    ]);

    // Create a dummy PDF
    $dummyPdfPath = storage_path('app/temp/dummy.pdf');
    File::ensureDirectoryExists(dirname($dummyPdfPath));
    File::put($dummyPdfPath, 'dummy pdf content');

    $service = app(BulkBolService::class);
    $method = new ReflectionMethod(BulkBolService::class, 'processPage');
    $method->setAccessible(true);

    $result = $method->invoke($service, 1, '1HGCR2F3XDA135424', $dummyPdfPath, $user);

    expect($result['status'])->toBe('matched')
        ->and($result['vin'])->toBe('1HGCR2F3XDA135424')
        ->and($result['ref'])->toBe($shipment->reference_no);

    $shipment->refresh();
    expect($shipment->shipment_status)->toBe(ShipmentStatus::Loaded)
        ->and($shipment->booked_without_title)->toBeFalse();

    expect($shipment->documents()->where('document_type', ShipmentDocumentType::BillOfLading)->exists())->toBeTrue();

    File::delete($dummyPdfPath);
});

it('returns wrong_status if shipment is not delivered', function () {
    $user = User::factory()->create();
    $shipment = Shipment::factory()->create([
        'shipment_status' => ShipmentStatus::Inland,
    ]);
    $vehicle = Vehicle::factory()->create([
        'shipment_id' => $shipment->id,
        'vin' => '1HGCR2F3XDA135424',
    ]);

    $dummyPdfPath = storage_path('app/temp/dummy.pdf');
    File::ensureDirectoryExists(dirname($dummyPdfPath));
    File::put($dummyPdfPath, 'dummy pdf content');

    $service = app(BulkBolService::class);
    $method = new ReflectionMethod(BulkBolService::class, 'processPage');
    $method->setAccessible(true);

    $result = $method->invoke($service, 1, '1HGCR2F3XDA135424', $dummyPdfPath, $user);

    expect($result['status'])->toBe('wrong_status');

    File::delete($dummyPdfPath);
});

it('formats summary correctly', function () {
    $service = app(BulkBolService::class);

    $results = [
        'total_pages' => 3,
        'matched' => [
            ['ref' => 'ANK0001','vin' => '1HGCR2F3XDA135424'],
        ],
        'unmatched' => [
            ['vin' => '1HGCR2F3XDA135425'],
        ],
        'wrong_status' => [
            ['ref' => 'ANK0002', 'vin' => '1HGCR2F3XDA135426', 'status_name' => 'Inland'],
        ],
        'failed' => [
            ['ref' => 'ANK0003', 'vin' => '1HGCR2F3XDA135427', 'error' => 'Disk full'],
        ],
        'no_vin' => [2],
    ];

    $summary = $service->formatSummary($results);

    expect($summary)->toContain('*Pages Processed:* 3')
        ->toContain('*Matched & Attached:* 1')
        ->toContain('*Unmatched VINs (no shipment found):* 1')
        ->toContain('*Wrong Status (not DELIVERED):* 1')
        ->toContain('*Pages with No VIN Detected:* 1')
        ->toContain('🚨 *Failed to Process (System Error):* 1')
        ->toContain('ANK0001 — 1HGCR2F3XDA135424')
        ->toContain('1HGCR2F3XDA135425')
        ->toContain('ANK0002 (1HGCR2F3XDA135426) — Inland')
        ->toContain('*No VIN Detected on Pages:* 2')
        ->toContain('*Failed Processing:*')
        ->toContain('ANK0003 (1HGCR2F3XDA135427)');
});