<?php

declare(strict_types=1);

use App\Data\CarrierReleaseData;
use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\ShipmentTracking;
use App\Models\Shipper;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\TelexReleaseSubmittedNotification;
use App\Services\CarrierEmailParserService;
use App\Services\CarrierReleaseFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('it parses sallaum lines telex release email correctly from eml fixture', function () {
    $parser = app(CarrierEmailParserService::class);
    $rawEmail = file_get_contents(base_path('TELEX_RELEASE.eml'));

    $data = $parser->parseRawEmail($rawEmail);

    expect($data)->not->toBeNull()
        ->and($data->isSallaum())->toBeTrue()
        ->and($data->isTelexRelease())->toBeTrue()
        ->and($data->vin)->toBe('1HGCV1F41KA162202')
        ->and($data->blNumber)->toBe('US00888436')
        ->and($data->vessel)->toBe('Glovis Crown')
        ->and($data->voyage)->toBe('26GW02')
        ->and($data->pol)->toBe('Jacksonville (USJAX)')
        ->and($data->pod)->toBe('Lagos (NGLOS)')
        ->and($data->consignee)->toBe('Ahab General Enterprises')
        ->and($data->officialReleaseText)->toContain('*** TELEX RELEASE ***')
        ->and($data->officialReleaseText)->toContain('ID: 4385269');
});

test('it parses grimaldi acl seaway bill email correctly from eml fixture', function () {
    $parser = app(CarrierEmailParserService::class);
    $rawEmail = file_get_contents(base_path('SEAWAY_BILL.eml'));

    $data = $parser->parseRawEmail($rawEmail);

    expect($data)->not->toBeNull()
        ->and($data->isGrimaldiAcl())->toBeTrue()
        ->and($data->isSeawayBill())->toBeTrue()
        ->and($data->vin)->toBe('1HGCR2F78GA225923')
        ->and($data->blNumber)->toBe('S3-30149305')
        ->and($data->pinNumber)->toBe('NEUR53CL')
        ->and($data->vessel)->toBe('GRANDE DAKAR')
        ->and($data->voyage)->toBe('GDK0726')
        ->and($data->pol)->toBe('JACKSONVILLE PORT')
        ->and($data->pod)->toBe('LAGOS - TIN CAN ISLAND')
        ->and($data->consignee)->toContain('IMMYKCONSULT LTD')
        ->and($data->officialReleaseText)->toContain('*** SEAWAY BILL ***')
        ->and($data->officialReleaseText)->toContain('Pin Number:                   NEUR53CL');
});

test('it leaves email unread and skips fulfillment if shipment status is not TELEX_REQUESTED', function () {
    Notification::fake();

    $user = User::factory()->create();
    $shipper = Shipper::factory()->create(['user_id' => $user->id]);
    $shipment = Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'shipment_status' => ShipmentStatus::Loaded,
    ]);

    $vehicle = Vehicle::factory()->create([
        'shipment_id' => $shipment->id,
        'vin' => '1HGCV1F41KA162202',
    ]);

    $releaseData = new CarrierReleaseData(
        carrier: 'sallaum',
        releaseType: 'telex_release',
        vin: '1HGCV1F41KA162202',
        blNumber: 'US00888436',
        pinNumber: null,
        vessel: 'Glovis Crown',
        voyage: '26GW02',
        pol: 'Jacksonville',
        pod: 'Lagos',
        consignee: 'Test Consignee',
        officialReleaseText: "*** TELEX RELEASE ***\nVessel: Glovis Crown",
    );

    $service = app(CarrierReleaseFulfillmentService::class);
    $result = $service->processRelease($releaseData);

    expect($result['status'])->toBe('skipped')
        ->and($result['reason'])->toBe('status_not_telex_requested')
        ->and($result['mark_as_seen'])->toBeFalse();

    $shipment->refresh();
    expect($shipment->shipment_status)->toBe(ShipmentStatus::Loaded)
        ->and($shipment->telex_release_text)->toBeNull();

    Notification::assertNothingSent();
});

test('it leaves email unread if vin does not exist in database', function () {
    $releaseData = new CarrierReleaseData(
        carrier: 'sallaum',
        releaseType: 'telex_release',
        vin: 'NONEXISTENTVIN123',
        blNumber: 'US00888436',
        pinNumber: null,
        vessel: null,
        voyage: null,
        pol: null,
        pod: null,
        consignee: null,
        officialReleaseText: '*** TELEX RELEASE ***',
    );

    $service = app(CarrierReleaseFulfillmentService::class);
    $result = $service->processRelease($releaseData);

    expect($result['status'])->toBe('skipped')
        ->and($result['reason'])->toBe('unknown_vin')
        ->and($result['mark_as_seen'])->toBeFalse();
});

test('it fulfills telex release when shipment is in TELEX_REQUESTED status and dispatches notifications', function () {
    Notification::fake();

    $user = User::factory()->create();
    $shipper = Shipper::factory()->create(['user_id' => $user->id]);
    $shipment = Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'shipment_status' => ShipmentStatus::TelexRequested,
        'bill_of_lading_number' => null,
    ]);

    $vehicle = Vehicle::factory()->create([
        'shipment_id' => $shipment->id,
        'vin' => '1HGCV1F41KA162202',
    ]);

    $officialText = "*** TELEX RELEASE ***\nVessel: Glovis Crown\nBL No: US00888436\nID: 4385269";

    $releaseData = new CarrierReleaseData(
        carrier: 'sallaum',
        releaseType: 'telex_release',
        vin: '1HGCV1F41KA162202',
        blNumber: 'US00888436',
        pinNumber: null,
        vessel: 'Glovis Crown',
        voyage: '26GW02',
        pol: 'Jacksonville',
        pod: 'Lagos',
        consignee: 'Ahab General Enterprises',
        officialReleaseText: $officialText,
    );

    $service = app(CarrierReleaseFulfillmentService::class);
    $result = $service->processRelease($releaseData);

    expect($result['status'])->toBe('fulfilled')
        ->and($result['mark_as_seen'])->toBeTrue();

    $shipment->refresh();
    expect($shipment->shipment_status)->toBe(ShipmentStatus::Completed)
        ->and($shipment->telex_release_text)->toBe($officialText)
        ->and($shipment->telex_released_at)->not->toBeNull()
        ->and($shipment->bill_of_lading_number)->toBe('US00888436');

    // Assert tracking record created
    expect(ShipmentTracking::where('shipment_id', $shipment->id)
        ->where('status', ShipmentStatus::Completed)
        ->where('note', 'like', '%carrier_email_automation%')
        ->exists())->toBeTrue();

    // Assert notification sent to shipper
    Notification::assertSentTo($user, TelexReleaseSubmittedNotification::class);
});

test('carrier:process-releases command runs with --file and --dry-run options', function () {
    $user = User::factory()->create();
    $shipper = Shipper::factory()->create(['user_id' => $user->id]);
    $shipment = Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'shipment_status' => ShipmentStatus::TelexRequested,
    ]);

    Vehicle::factory()->create([
        'shipment_id' => $shipment->id,
        'vin' => '1HGCV1F41KA162202',
    ]);

    $this->artisan('carrier:process-releases', [
        '--file' => base_path('TELEX_RELEASE.eml'),
        '--dry-run' => true,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('Running in DRY-RUN mode')
        ->expectsOutputToContain('Carrier: sallaum | VIN: 1HGCV1F41KA162202');

    // Confirm DB was untouched due to dry-run
    $shipment->refresh();
    expect($shipment->shipment_status)->toBe(ShipmentStatus::TelexRequested)
        ->and($shipment->telex_release_text)->toBeNull();
});
