<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Port;
use App\Models\Shipment;
use App\Models\Shipper;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\BookedWithoutTitleReminderNotification;
use App\Notifications\InvoiceOverdueReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('super_admin');
    Role::findOrCreate('shipper');
});

test('reminders:process sends title reminders for shipments booked without title older than 3 days and touches updated_at', function (): void {
    Notification::fake();

    $shipperUser = User::factory()->create();
    $shipperUser->assignRole('shipper');
    $shipper = Shipper::factory()->create(['user_id' => $shipperUser->id]);

    $port = Port::factory()->create([
        'terminal_name' => 'Red Hook Container Terminal',
        'terminal_address' => '70 Hamilton Ave, Brooklyn',
        'terminal_state' => 'NY',
        'terminal_zipcode' => '11231',
    ]);

    $pastDate = now()->subDays(4);

    $shipment = Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'origin_port_id' => $port->id,
        'booked_without_title' => true,
        'shipment_status' => ShipmentStatus::Booking,
        'updated_at' => $pastDate,
    ]);

    Vehicle::factory()->create([
        'shipment_id' => $shipment->id,
        'vin' => '1HGCR2F80DA033464',
        'year' => 2013,
        'make' => 'Honda',
        'model' => 'Accord',
    ]);

    // Force updated_at timestamp to 4 days ago
    Shipment::where('id', $shipment->id)->update(['updated_at' => $pastDate]);

    $this->artisan('reminders:process --type=titles')
        ->assertSuccessful();

    Notification::assertSentTo($shipperUser, BookedWithoutTitleReminderNotification::class);

    expect(ActivityLog::where('shipment_id', $shipment->id)->where('action', 'title_reminder_sent')->exists())->toBeTrue()
        ->and($shipment->fresh()->updated_at->gt($pastDate))->toBeTrue();
});

test('reminders:process skips title reminders for shipments updated less than 3 days ago', function (): void {
    Notification::fake();

    $shipperUser = User::factory()->create();
    $shipper = Shipper::factory()->create(['user_id' => $shipperUser->id]);

    $shipment = Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'booked_without_title' => true,
        'updated_at' => now()->subDays(2),
    ]);

    Shipment::where('id', $shipment->id)->update(['updated_at' => now()->subDays(2)]);

    $this->artisan('reminders:process --type=titles')
        ->assertSuccessful();

    Notification::assertNotSentTo($shipperUser, BookedWithoutTitleReminderNotification::class);
});

test('reminders:process skips title reminders for shipments where booked_without_title is false', function (): void {
    Notification::fake();

    $shipperUser = User::factory()->create();
    $shipper = Shipper::factory()->create(['user_id' => $shipperUser->id]);

    $shipment = Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'booked_without_title' => false,
        'updated_at' => now()->subDays(5),
    ]);

    Shipment::where('id', $shipment->id)->update(['updated_at' => now()->subDays(5)]);

    $this->artisan('reminders:process --type=titles')
        ->assertSuccessful();

    Notification::assertNotSentTo($shipperUser, BookedWithoutTitleReminderNotification::class);
});

test('reminders:process sends overdue invoice reminders for completed unpaid invoices older than 21 days', function (): void {
    Notification::fake();

    $shipperUser = User::factory()->create();
    $shipperUser->assignRole('shipper');
    $shipper = Shipper::factory()->create(['user_id' => $shipperUser->id]);

    $shipment = Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'payment_status' => PaymentStatus::AwaitingBL,
    ]);

    $pastDate = now()->subWeeks(4);

    $invoice = Invoice::factory()->create([
        'shipment_id' => $shipment->id,
        'status' => InvoiceStatus::Completed,
        'total_amount' => 1250.00,
        'updated_at' => $pastDate,
    ]);

    Invoice::where('id', $invoice->id)->update(['updated_at' => $pastDate]);

    $this->artisan('reminders:process --type=invoices')
        ->assertSuccessful();

    Notification::assertSentTo($shipperUser, InvoiceOverdueReminderNotification::class);

    expect(ActivityLog::where('shipment_id', $shipment->id)->where('action', 'invoice_overdue_reminder_sent')->exists())->toBeTrue();
});

test('reminders:process skips overdue invoice reminders when shipment payment_status is paid', function (): void {
    Notification::fake();

    $shipperUser = User::factory()->create();
    $shipper = Shipper::factory()->create(['user_id' => $shipperUser->id]);

    $shipment = Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'payment_status' => PaymentStatus::Paid,
    ]);

    $pastDate = now()->subWeeks(4);

    $invoice = Invoice::factory()->create([
        'shipment_id' => $shipment->id,
        'status' => InvoiceStatus::Completed,
        'updated_at' => $pastDate,
    ]);

    Invoice::where('id', $invoice->id)->update(['updated_at' => $pastDate]);

    $this->artisan('reminders:process --type=invoices')
        ->assertSuccessful();

    Notification::assertNotSentTo($shipperUser, InvoiceOverdueReminderNotification::class);
});

test('reminders:process respects 7-day cooldown for subsequent overdue invoice reminders', function (): void {
    Notification::fake();

    $shipperUser = User::factory()->create();
    $shipper = Shipper::factory()->create(['user_id' => $shipperUser->id]);

    $shipment = Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'payment_status' => PaymentStatus::AwaitingBL,
    ]);

    $pastDate = now()->subWeeks(5);

    $invoice = Invoice::factory()->create([
        'shipment_id' => $shipment->id,
        'status' => InvoiceStatus::Completed,
        'updated_at' => $pastDate,
    ]);

    Invoice::where('id', $invoice->id)->update(['updated_at' => $pastDate]);

    // Create an activity log indicating a reminder was already sent 3 days ago (cooldown active)
    ActivityLog::create([
        'shipment_id' => $shipment->id,
        'user_id' => $shipperUser->id,
        'action' => 'invoice_overdue_reminder_sent',
        'created_at' => now()->subDays(3),
        'updated_at' => now()->subDays(3),
    ]);

    $this->artisan('reminders:process --type=invoices')
        ->assertSuccessful();

    Notification::assertNotSentTo($shipperUser, InvoiceOverdueReminderNotification::class);

    // Fast-forward cooldown: Update the log to 8 days ago
    ActivityLog::where('shipment_id', $shipment->id)->update([
        'created_at' => now()->subDays(8),
        'updated_at' => now()->subDays(8),
    ]);

    $this->artisan('reminders:process --type=invoices')
        ->assertSuccessful();

    Notification::assertSentTo($shipperUser, InvoiceOverdueReminderNotification::class);
});

test('reminders:process dry-run mode does not modify records or dispatch notifications', function (): void {
    Notification::fake();

    $shipperUser = User::factory()->create();
    $shipper = Shipper::factory()->create(['user_id' => $shipperUser->id]);

    $pastDate = now()->subDays(5);

    $shipment = Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'booked_without_title' => true,
        'updated_at' => $pastDate,
    ]);

    Shipment::where('id', $shipment->id)->update(['updated_at' => $pastDate]);

    $this->artisan('reminders:process --dry-run')
        ->assertSuccessful()
        ->expectsOutputToContain('DRY-RUN mode');

    Notification::assertNothingSent();
    expect($shipment->fresh()->updated_at->timestamp)->toBe($pastDate->timestamp);
});

test('booked without title notification renders properly for mail and database channels', function (): void {
    $shipperUser = User::factory()->create();
    $shipper = Shipper::factory()->create(['user_id' => $shipperUser->id]);

    $port = Port::factory()->create([
        'terminal_name' => 'Red Hook Terminal',
        'terminal_address' => '70 Hamilton Ave',
        'terminal_state' => 'NY',
        'terminal_zipcode' => '11231',
        'terminal_phone' => '718-555-0100',
    ]);

    $shipment = Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'origin_port_id' => $port->id,
        'reference_no' => 'ANK-TEST-001',
    ]);

    $vehicle = Vehicle::factory()->create([
        'shipment_id' => $shipment->id,
        'vin' => '1HGCR2F80DA999999',
        'year' => 2020,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    $notification = new BookedWithoutTitleReminderNotification($shipment);

    $dbData = $notification->toArray($shipperUser);
    expect($dbData['title'])->toBe('Title Document Required')
        ->and($dbData['reference_no'])->toBe('ANK-TEST-001')
        ->and($dbData['vin'])->toBe('1HGCR2F80DA999999');

    $mailMessage = $notification->toMail($shipperUser);
    expect($mailMessage->subject)->toContain('Title Document Required')
        ->and($mailMessage->subject)->toContain('ANK-TEST-001');

    $whatsApp = $notification->toWhatsApp($shipperUser);
    expect($whatsApp['body'])->toContain('Title Document Required')
        ->and($whatsApp['body'])->toContain('FedEx')
        ->and($whatsApp['body'])->toContain('Red Hook Terminal');
});

test('overdue invoice reminder notification renders properly for mail and database channels', function (): void {
    $shipperUser = User::factory()->create();
    $shipper = Shipper::factory()->create(['user_id' => $shipperUser->id]);

    $shipment = Shipment::factory()->create([
        'shipper_id' => $shipper->id,
        'reference_no' => 'ANK-INV-001',
    ]);

    $invoice = Invoice::factory()->create([
        'shipment_id' => $shipment->id,
        'invoice_number' => 'INV-99999',
        'total_amount' => 2500.00,
    ]);

    $notification = new InvoiceOverdueReminderNotification($shipment, $invoice);

    $dbData = $notification->toArray($shipperUser);
    expect($dbData['title'])->toBe('Payment Reminder: Invoice Overdue')
        ->and($dbData['reference_no'])->toBe('ANK-INV-001')
        ->and($dbData['invoice_id'])->toBe($invoice->id);

    $mailMessage = $notification->toMail($shipperUser);
    expect($mailMessage->subject)->toContain('Payment Reminder: Invoice Overdue')
        ->and($mailMessage->subject)->toContain('INV-99999');

    $whatsApp = $notification->toWhatsApp($shipperUser);
    expect($whatsApp['body'])->toContain('Payment Reminder: Invoice Overdue')
        ->and($whatsApp['body'])->toContain('INV-99999')
        ->and($whatsApp['body'])->toContain('2,500.00');
});
