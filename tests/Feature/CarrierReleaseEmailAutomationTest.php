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
        ->and($data->officialReleaseText)->toContain('Pin Number:                   NEUR53CL')
        ->and($data->officialReleaseText)->toContain('The Seaway Bill email & proper identification is to be taken directly to the Release desk.')
        ->and($data->officialReleaseText)->toContain('Notification Rule ID: 17278931734');
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

test('it decodes quoted-printable single-part email and cleans =20 artifacts', function () {
    $parser = app(CarrierEmailParserService::class);

    $rawEmail = <<<'EML'
Date: Mon, 28 Sep 2026 16:18:33 -0400
Subject: *** TELEX RELEASE *** US00888436 - 26GW02 - Jacksonville (USJAX) TO Lagos (NGLOS) Port - 1HGCV1F41KA162202
From: customer.service@sallaumlines.us
Content-Type: text/plain; charset="UTF-8"
Content-Transfer-Encoding: quoted-printable

*** TELEX RELEASE ***

Vessel: Glovis Crown
Voyage: 26GW02            =20
POL: Jacksonville (USJAX)                   =20
POD: Lagos (NGLOS)           =20
BL No: US00888436 =20
Timestamp: 2026-09-28
Item(s):
- Vin: 1HGCV1F41KA162202
- Make/Model: Honda Accord
- Year: 2019

Agent: ANKA SHIPPING & LOGISTICS LLC=20
Consignee: Ahab General Enterprises=20

Please release this shipment to receiver without presentation of the Origin=
al Bills of lading, but against proper identification only.
All local charges are for the receiver's account.
ID: 4385269
EML;

    $data = $parser->parseRawEmail($rawEmail);

    expect($data)->not->toBeNull()
        ->and($data->vin)->toBe('1HGCV1F41KA162202')
        ->and($data->voyage)->toBe('26GW02')
        ->and($data->officialReleaseText)->not->toContain('=20')
        ->and($data->officialReleaseText)->toContain('Original Bills of lading')
        ->and($data->officialReleaseText)->toContain('ID: 4385269');
});

test('it parses single-part html seaway bill email and strips html tags', function () {
    $parser = app(CarrierEmailParserService::class);

    $rawEmail = <<<'EML'
Date: Mon, 28 Sep 2026 16:15:17 -0400
Subject: *** SEAWAY BILL *** - S3-30192770 - GDK0726 - 5NPE34AF6HH438057 - JACKSONVILLE PORT TO LAGOS - TIN C
From: "Atlas System" <donotreply@aclcargo.com>
Content-Type: text/html;
Content-Transfer-Encoding: quoted-printable

<pre>THIS IS AN AUTOMATED MESSAGE. PLEASE DO NOT REPLY DIRECTLY TO THIS EMAIL
<pre> Date: 09/28/2026 20:15:09<br><br><strong><font size=3D4>*** SEAWAY BILL ***</font></strong></pre><br><br><pre><b>Vessel/Voyage:                </b>GRANDE DAKAR/GDK0726</pre><br><pre><b>Port of Load:                 </b>JACKSONVILLE PORT</pre><br><pre><b>Port of Discharge:            </b>LAGOS - TIN CAN ISLAND</pre><br><pre><b>BL/Shipment No:               </b>S3-30192770</pre><br><pre><b>VIN/Container:                </b>5NPE34AF6HH438057</pre><br><pre><b>Commodity:                    </b>HYUNDAI SONATA</pre><br><pre><b>Consignee:                    </b>Global village Auto Handlers<br>                              258 KATSINA ROAD<br>                              KANO , NIGERIA<br></pre><br><pre><b>Clearing Agent:               </b></pre><br><pre><b>Pin Number:                   </b>5JNNQUDO</pre><br><br>The Seaway Bill email & proper identification is to be taken directly to the Release desk.
<br>**Not the customer care desk that PAD (originals) are printed**<br><br>Please provide the releasing dest agent the PIN associated.<br>If the PIN is accurate, the Delivery Order will be printed & cargo will be released.<br>If the PIN is inaccurate, please contact the Shipper as cargo will not be released.(Notification Rule ID: 17278931734)(19218171878)
EML;

    $data = $parser->parseRawEmail($rawEmail);

    expect($data)->not->toBeNull()
        ->and($data->isGrimaldiAcl())->toBeTrue()
        ->and($data->isSeawayBill())->toBeTrue()
        ->and($data->vin)->toBe('5NPE34AF6HH438057')
        ->and($data->blNumber)->toBe('S3-30192770')
        ->and($data->pinNumber)->toBe('5JNNQUDO')
        ->and($data->vessel)->toBe('GRANDE DAKAR')
        ->and($data->voyage)->toBe('GDK0726')
        ->and($data->officialReleaseText)->not->toContain('<pre>')
        ->and($data->officialReleaseText)->not->toContain('<b>')
        ->and($data->officialReleaseText)->toContain('Pin Number:                   5JNNQUDO')
        ->and($data->officialReleaseText)->toContain('The Seaway Bill email & proper identification is to be taken directly to the Release desk.')
        ->and($data->officialReleaseText)->toContain('Notification Rule ID: 17278931734');
});
