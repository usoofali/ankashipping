<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\Shipper;
use App\Models\User;
use App\Models\Vehicle;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Services\BotService;
use App\Modules\WhatsApp\Services\WhatsAppService;
use App\Notifications\TelexReleaseRequestedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery\MockInterface;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'workflow.submit_telex']);
    Role::firstOrCreate(['name' => 'staff_operator'])->givePermissionTo('workflow.submit_telex');
});

test('bot menu choice 5 triggers telex awaiting vin state', function () {
    $this->mock(WhatsAppService::class, function (MockInterface $mock) {
        $mock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($phone, $text) {
                return $phone === '+1234567890' && str_contains($text, 'Request Telex Release');
            });
    });

    $user = User::factory()->create();
    $shipper = Shipper::factory()->create(['user_id' => $user->id]);
    $conversation = WhatsAppConversation::create([
        'phone_number' => '+1234567890',
        'contact_type' => Shipper::class,
        'contact_id' => $shipper->id,
    ]);

    $botService = app(BotService::class);
    $botService->handle($conversation, [
        'text' => ['body' => '5'],
    ]);

    $state = $conversation->menuState()->first();
    expect($state)->not->toBeNull()
        ->and($state->current_step)->toBe('telex_awaiting_vin');
});

test('option a: returns telex instantly if available and paid', function () {
    $telexText = "*** TELEX RELEASE ***\nReleased automatically by Sallaum Lines.";

    $this->mock(WhatsAppService::class, function (MockInterface $mock) use ($telexText) {
        $mock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($phone, $text) use ($telexText) {
                return $phone === '+1234567890' && str_contains($text, $telexText);
            });
    });

    $user = User::factory()->create();
    $shipper = Shipper::factory()->create(['user_id' => $user->id]);
    $shipment = Shipment::factory()->create([
        'reference_no' => 'ANKA-TEST-100',
        'shipper_id' => $shipper->id,
        'payment_status' => PaymentStatus::Paid,
        'telex_release_text' => $telexText,
    ]);

    $conversation = WhatsAppConversation::create([
        'phone_number' => '+1234567890',
        'contact_type' => Shipper::class,
        'contact_id' => $shipper->id,
    ]);

    $conversation->menuState()->create(['current_step' => 'telex_awaiting_vin']);

    $botService = app(BotService::class);
    $botService->handle($conversation, [
        'text' => ['body' => 'ANKA-TEST-100'],
    ]);

    expect($conversation->menuState()->first())->toBeNull();
});

test('option b: automatically requests telex when not available and paid', function () {
    Notification::fake();

    $this->mock(WhatsAppService::class, function (MockInterface $mock) {
        $mock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($phone, $text) {
                return $phone === '+1234567890' && str_contains($text, 'TELEX RELEASE REQUEST SUBMITTED');
            });
    });

    $user = User::factory()->create();
    $shipper = Shipper::factory()->create(['user_id' => $user->id]);
    $shipment = Shipment::factory()->create([
        'reference_no' => 'ANKA-TEST-200',
        'shipper_id' => $shipper->id,
        'payment_status' => PaymentStatus::Paid,
        'shipment_status' => ShipmentStatus::Loaded,
        'telex_release_text' => null,
    ]);

    $staffUser = User::factory()->create();
    $staffUser->assignRole('staff_operator');

    $conversation = WhatsAppConversation::create([
        'phone_number' => '+1234567890',
        'contact_type' => Shipper::class,
        'contact_id' => $shipper->id,
    ]);

    $conversation->menuState()->create(['current_step' => 'telex_awaiting_vin']);

    $botService = app(BotService::class);
    $botService->handle($conversation, [
        'text' => ['body' => 'ANKA-TEST-200'],
    ]);

    $shipment->refresh();
    expect($shipment->shipment_status)->toBe(ShipmentStatus::TelexRequested)
        ->and($conversation->menuState()->first())->toBeNull();

    Notification::assertSentTo($staffUser, TelexReleaseRequestedNotification::class);
});

test('blocks telex request if payment status is not paid', function () {
    $this->mock(WhatsAppService::class, function (MockInterface $mock) {
        $mock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($phone, $text) {
                return $phone === '+1234567890' && str_contains($text, 'Payment Required');
            });
    });

    $user = User::factory()->create();
    $shipper = Shipper::factory()->create(['user_id' => $user->id]);
    $shipment = Shipment::factory()->create([
        'reference_no' => 'ANKA-TEST-300',
        'shipper_id' => $shipper->id,
        'payment_status' => PaymentStatus::AwaitingPayment,
        'telex_release_text' => null,
    ]);

    $conversation = WhatsAppConversation::create([
        'phone_number' => '+1234567890',
        'contact_type' => Shipper::class,
        'contact_id' => $shipper->id,
    ]);

    $conversation->menuState()->create(['current_step' => 'telex_awaiting_vin']);

    $botService = app(BotService::class);
    $botService->handle($conversation, [
        'text' => ['body' => 'ANKA-TEST-300'],
    ]);

    $shipment->refresh();
    expect($shipment->shipment_status)->not->toBe(ShipmentStatus::TelexRequested);
});

test('can request telex using last 6 characters of vin via bot', function () {
    Notification::fake();

    $this->mock(WhatsAppService::class, function (MockInterface $mock) {
        $mock->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($phone, $text) {
                return $phone === '+1234567890' && str_contains($text, 'TELEX RELEASE REQUEST SUBMITTED');
            });
    });

    $user = User::factory()->create();
    $shipper = Shipper::factory()->create(['user_id' => $user->id]);
    $shipment = Shipment::factory()->create([
        'reference_no' => 'ANKA-VIN-TEST',
        'shipper_id' => $shipper->id,
        'payment_status' => PaymentStatus::Paid,
        'shipment_status' => ShipmentStatus::Loaded,
        'telex_release_text' => null,
    ]);

    Vehicle::factory()->create([
        'shipment_id' => $shipment->id,
        'vin' => 'JTNK4RBE9K3048166',
    ]);

    $conversation = WhatsAppConversation::create([
        'phone_number' => '+1234567890',
        'contact_type' => Shipper::class,
        'contact_id' => $shipper->id,
    ]);

    $conversation->menuState()->create(['current_step' => 'telex_awaiting_vin']);

    $botService = app(BotService::class);
    // User submits only the last 6 characters of the VIN
    $botService->handle($conversation, [
        'text' => ['body' => '048166'],
    ]);

    $shipment->refresh();
    expect($shipment->shipment_status)->toBe(ShipmentStatus::TelexRequested);
});
