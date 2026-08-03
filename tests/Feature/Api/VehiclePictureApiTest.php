<?php

declare(strict_types=1);

use App\Enums\VehicleDocumentType;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Models\VehicleDocumentFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('returns 422 when vin is invalid', function (): void {
    $response = $this->getJson('/api/vehicles/invalid!vin/pictures');

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'pictures' => [],
        ]);
});

test('returns vehicle pictures from local database when vehicle exists', function (): void {
    $vin = '1HGCR2F83HA123456';

    Vehicle::factory()->create([
        'vin' => $vin,
        'make' => 'Honda',
        'model' => 'Accord',
        'year' => '2017',
        'api_snapshot' => [
            'car_photo' => [
                'photo' => [
                    'https://cs.copart.com/v1/AUTH_svc/images/photo1.jpg',
                    'https://cs.copart.com/v1/AUTH_svc/images/photo2.jpg',
                ],
            ],
        ],
    ]);

    $response = $this->getJson("/api/vehicles/{$vin}/pictures");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'vin' => $vin,
            'make' => 'Honda',
            'model' => 'Accord',
            'year' => '2017',
            'pictures' => [
                'https://cs.copart.com/v1/AUTH_svc/images/photo1.jpg',
                'https://cs.copart.com/v1/AUTH_svc/images/photo2.jpg',
            ],
        ]);
});

test('resolves vehicle pictures via query parameter', function (): void {
    $vin = '1HGCR2F83HA654321';

    Vehicle::factory()->create([
        'vin' => $vin,
        'make' => 'Toyota',
        'model' => 'Camry',
        'year' => '2020',
        'api_snapshot' => [
            'car_photo' => [
                'photo' => [
                    'https://cs.copart.com/v1/AUTH_svc/images/toyota1.jpg',
                ],
            ],
        ],
    ]);

    $response = $this->getJson("/api/vehicles/pictures?vin={$vin}");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'vin' => $vin,
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => '2020',
            'pictures' => [
                'https://cs.copart.com/v1/AUTH_svc/images/toyota1.jpg',
            ],
        ]);
});

test('returns 404 when vehicle is not found locally and external api is unavailable', function (): void {
    Http::fake([
        '*' => Http::response([], 404),
    ]);

    $vin = '1HGCR2F83HA999999';

    $response = $this->getJson("/api/vehicles/{$vin}/pictures");

    $response->assertStatus(404)
        ->assertJson([
            'success' => false,
            'pictures' => [],
        ]);
});

test('includes image files from vehicle documents in pictures array and excludes non-images', function (): void {
    $vin = 'WDCGG8HB7AF354346';

    $vehicle = Vehicle::factory()->create([
        'vin' => $vin,
        'make' => 'Mercedes-Benz',
        'model' => 'G-Class',
        'year' => '2010',
    ]);

    $doc = VehicleDocument::create([
        'vehicle_id' => $vehicle->id,
        'document_type' => VehicleDocumentType::TitleDocument,
    ]);

    $imageFile = VehicleDocumentFile::create([
        'vehicle_document_id' => $doc->id,
        'path' => 'vehicle_documents/test-photo.jpg',
        'original_name' => 'car_front.jpg',
    ]);

    $pdfFile = VehicleDocumentFile::create([
        'vehicle_document_id' => $doc->id,
        'path' => 'vehicle_documents/invoice.pdf',
        'original_name' => 'invoice.pdf',
    ]);

    expect($imageFile->isImage())->toBeTrue();
    expect($pdfFile->isImage())->toBeFalse();
    expect($imageFile->storage_path)->toBe('vehicle_documents/test-photo.jpg');

    $response = $this->getJson("/api/vehicles/pictures?vin={$vin}");

    $response->assertStatus(200);

    $pictures = $response->json('pictures');
    expect($pictures)->toBeArray()->toHaveCount(1);
    expect($pictures[0])->toContain("vehicles/{$vehicle->id}/documents/files/{$imageFile->id}/signed");
});
