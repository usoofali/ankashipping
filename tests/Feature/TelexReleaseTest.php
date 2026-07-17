<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\ShipmentTracking;
use App\Models\Shipper;
use App\Models\User;
use App\Modules\WhatsApp\Services\TelexRequestService;
use App\Notifications\TelexReleaseSubmittedNotification;
use App\ShippingWorkflow\ShippingWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'workflow.request_telex']);
    Permission::firstOrCreate(['name' => 'workflow.submit_telex']);

    $shipperRole = Role::firstOrCreate(['name' => 'shipper']);
    $shipperRole->givePermissionTo('workflow.request_telex');

    $staffRole = Role::firstOrCreate(['name' => 'staff_operator']);
    $staffRole->givePermissionTo(['workflow.request_telex', 'workflow.submit_telex']);

    $this->workflow = app(ShippingWorkflow::class);
});

test('shipper cannot request telex if invoice is not paid', function () {
    $user = User::factory()->create();
    $user->assignRole('shipper');

    $shipper = Shipper::factory()->create(['user_id' => $user->id]);
    $shipment = Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'shipment_status' => ShipmentStatus::Loaded,
        'payment_status' => PaymentStatus::AwaitingPayment,
    ]);

    expect($this->workflow->canRequestTelex($shipment, $user))->toBeFalse();
});

test('shipper can request telex if invoice is paid and shipment is loaded', function () {
    $user = User::factory()->create();
    $user->assignRole('shipper');

    $shipper = Shipper::factory()->create(['user_id' => $user->id]);
    $shipment = Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'shipment_status' => ShipmentStatus::Loaded,
        'payment_status' => PaymentStatus::Paid,
    ]);

    expect($this->workflow->canRequestTelex($shipment, $user))->toBeTrue();
});

test('staff operator with submit_telex permission can submit telex release', function () {
    $user = User::factory()->create();
    $user->assignRole('staff_operator');

    $shipment = Shipment::factory()->create([
        'shipment_status' => ShipmentStatus::TelexRequested,
    ]);

    expect($this->workflow->canSubmitTelexRelease($shipment, $user))->toBeTrue();
});

test('submitting official telex text transitions shipment to completed status and sends notifications', function () {
    Notification::fake();

    $user = User::factory()->create();
    $user->assignRole('shipper');
    $shipper = Shipper::factory()->create(['user_id' => $user->id]);

    $shipment = Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'shipment_status' => ShipmentStatus::TelexRequested,
        'bill_of_lading_number' => 'US00861832',
    ]);

    $telexText = "*** TELEX RELEASE ***\nVessel: Glovis Sunrise\nBL No: US00861832\nPlease release this shipment to receiver.";

    $shipment->update([
        'telex_release_text' => $telexText,
        'telex_released_at' => now(),
        'shipment_status' => ShipmentStatus::Completed,
    ]);

    $telexService = app(TelexRequestService::class);
    $telexService->sendTelexNotificationToParticipants($shipment);

    $shipment->refresh();
    expect($shipment->shipment_status)->toBe(ShipmentStatus::Completed)
        ->and($shipment->telex_release_text)->toBe($telexText)
        ->and($shipment->telex_released_at)->not->toBeNull();

    Notification::assertSentTo($user, TelexReleaseSubmittedNotification::class);
});

test('telex request service requestTelexRelease updates status to telex requested and logs activity', function () {
    $user = User::factory()->create();
    $shipper = Shipper::factory()->create(['user_id' => $user->id]);
    $shipment = Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'shipment_status' => ShipmentStatus::Loaded,
    ]);

    $service = app(TelexRequestService::class);
    $service->requestTelexRelease($shipment, $user, 'test_source');

    $shipment->refresh();
    expect($shipment->shipment_status)->toBe(ShipmentStatus::TelexRequested)
        ->and(ShipmentTracking::where('shipment_id', $shipment->id)->where('status', ShipmentStatus::TelexRequested)->exists())->toBeTrue();
});

test('telex request service fulfillTelexRelease records text and marks shipment completed', function () {
    Notification::fake();

    $user = User::factory()->create();
    $shipper = Shipper::factory()->create(['user_id' => $user->id]);
    $shipment = Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'shipment_status' => ShipmentStatus::TelexRequested,
    ]);

    $telexText = "*** TELEX RELEASE ***\nOfficial release text.";
    $service = app(TelexRequestService::class);
    $service->fulfillTelexRelease($shipment, $telexText, $user, 'test_source');

    $shipment->refresh();
    expect($shipment->shipment_status)->toBe(ShipmentStatus::Completed)
        ->and($shipment->telex_release_text)->toBe($telexText);

    Notification::assertSentTo($user, TelexReleaseSubmittedNotification::class);
});
