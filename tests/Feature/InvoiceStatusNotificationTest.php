<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\ShipmentStatus;
use App\Models\Invoice;
use App\Models\Shipment;
use App\Models\Shipper;
use App\Models\User;
use App\Notifications\InvoiceStatusChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('super_admin');
    Role::findOrCreate('staff_admin');
    Role::findOrCreate('shipper');

    Permission::findOrCreate('invoices.manage');
    Role::findByName('super_admin')->givePermissionTo('invoices.manage');
});

test('invoice status change to completed sends database notification to admin and mail notification to shipper', function (): void {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $shipperUser = User::factory()->create();
    $shipperUser->assignRole('shipper');
    $shipper = Shipper::factory()->create(['user_id' => $shipperUser->id]);

    $shipment = Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'shipment_status' => ShipmentStatus::Loaded,
    ]);

    $invoice = Invoice::factory()->create([
        'shipment_id' => $shipment->id,
        'status' => InvoiceStatus::Draft,
        'total_amount' => 500.00,
    ]);

    $this->actingAs($admin);

    Volt::test('pages::shipments.show', ['shipment' => $shipment])
        ->set('pendingInvoiceStatus', InvoiceStatus::Completed->value)
        ->call('confirmInvoiceStatusChange');

    Notification::assertSentTo($admin, InvoiceStatusChangedNotification::class, function ($notification, $channels) {
        return $channels === ['database'];
    });

    Notification::assertSentTo($shipperUser, InvoiceStatusChangedNotification::class, function ($notification, $channels) {
        return in_array('mail', $channels, true);
    });
});
