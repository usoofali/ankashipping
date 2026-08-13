<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\ShipmentStatus;
use App\Models\Invoice;
use App\Models\Shipment;
use App\Models\User;
use App\ShippingWorkflow\ShippingWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Permission::findOrCreate('workflow.complete');
});

test('user with workflow.complete permission evaluates to true for invoice workflow actions', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo('workflow.complete');

    $shipment = Shipment::factory()->create([
        'shipment_status' => ShipmentStatus::Loaded,
    ]);

    $invoice = Invoice::factory()->create([
        'shipment_id' => $shipment->id,
        'status' => InvoiceStatus::Draft,
    ]);

    $workflow = app(ShippingWorkflow::class);

    // Verify user with workflow.complete returns true
    expect($workflow->canViewInvoice($shipment, $user))->toBeTrue();
    expect($workflow->canClearInvoice($shipment, $user))->toBeTrue();
    expect($workflow->canCompleteInvoice($shipment, $user))->toBeTrue();
    expect($workflow->canEditInvoice($shipment, $user))->toBeTrue();
});

test('user without workflow.complete permission does not evaluate to true for invoice workflow actions', function (): void {
    $user = User::factory()->create();
    // Do not give permission

    $shipment = Shipment::factory()->create([
        'shipment_status' => ShipmentStatus::Loaded,
    ]);

    $invoice = Invoice::factory()->create([
        'shipment_id' => $shipment->id,
        'status' => InvoiceStatus::Draft,
    ]);

    $workflow = app(ShippingWorkflow::class);

    // Verify user without permission returns false
    expect($workflow->canViewInvoice($shipment, $user))->toBeFalse();
    expect($workflow->canClearInvoice($shipment, $user))->toBeFalse();
    expect($workflow->canCompleteInvoice($shipment, $user))->toBeFalse();
    expect($workflow->canEditInvoice($shipment, $user))->toBeFalse();
});
