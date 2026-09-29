<?php

declare(strict_types=1);

use App\Enums\EmailLogStatus;
use App\Models\ActivityLog;
use App\Models\EmailAttempt;
use App\Models\EmailLog;
use App\Models\Invoice;
use App\Models\Shipment;
use App\Models\Shipper;
use App\Models\User;
use App\Services\HousekeepingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('super_admin');
});

test('super admin can view housekeeping section on system config page', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    Volt::test('pages::settings.⚡system-config')
        ->assertSee('Database Housekeeping & Retention')
        ->assertSee('Notifications')
        ->assertSee('Email Logs & Attempts')
        ->assertSee('Failed Queue Jobs')
        ->assertSee('Activity Audit Logs')
        ->assertSee('Purge All Older than 30 Days');
});

test('housekeeping service prunes notifications older than given days', function (): void {
    $service = app(HousekeepingService::class);

    // Old notifications (>30 days)
    DB::table('notifications')->insert([
        [
            'id' => Str::uuid()->toString(),
            'type' => 'App\Notifications\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => 1,
            'data' => json_encode(['msg' => 'old 1']),
            'read_at' => null,
            'created_at' => now()->subDays(45),
            'updated_at' => now()->subDays(45),
        ],
        [
            'id' => Str::uuid()->toString(),
            'type' => 'App\Notifications\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => 1,
            'data' => json_encode(['msg' => 'old 2']),
            'read_at' => now()->subDays(35),
            'created_at' => now()->subDays(40),
            'updated_at' => now()->subDays(35),
        ],
    ]);

    // Recent notification (<30 days)
    $recentId = Str::uuid()->toString();
    DB::table('notifications')->insert([
        'id' => $recentId,
        'type' => 'App\Notifications\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => 1,
        'data' => json_encode(['msg' => 'recent']),
        'read_at' => null,
        'created_at' => now()->subDays(5),
        'updated_at' => now()->subDays(5),
    ]);

    expect(DB::table('notifications')->count())->toBe(3);

    $deleted = $service->pruneNotifications(30);

    expect($deleted)->toBe(2);
    expect(DB::table('notifications')->count())->toBe(1);
    expect(DB::table('notifications')->where('id', $recentId)->exists())->toBeTrue();
});

test('housekeeping service prunes email logs and cascades to email attempts', function (): void {
    $service = app(HousekeepingService::class);

    // Old email log (>30 days)
    $oldLog = EmailLog::query()->create([
        'mailable_class' => 'App\Mail\TestMail',
        'recipient_email' => 'old@example.com',
        'status' => EmailLogStatus::Sent,
    ]);
    DB::table('email_logs')->where('id', $oldLog->id)->update([
        'created_at' => now()->subDays(40),
        'updated_at' => now()->subDays(40),
    ]);

    EmailAttempt::query()->create([
        'email_log_id' => $oldLog->id,
        'attempted_at' => now()->subDays(40),
    ]);

    // Recent email log (<30 days)
    $recentLog = EmailLog::query()->create([
        'mailable_class' => 'App\Mail\TestMail',
        'recipient_email' => 'recent@example.com',
        'status' => EmailLogStatus::Sent,
    ]);
    DB::table('email_logs')->where('id', $recentLog->id)->update([
        'created_at' => now()->subDays(5),
        'updated_at' => now()->subDays(5),
    ]);

    EmailAttempt::query()->create([
        'email_log_id' => $recentLog->id,
        'attempted_at' => now()->subDays(5),
    ]);

    expect(EmailLog::count())->toBe(2);
    expect(EmailAttempt::count())->toBe(2);

    $deleted = $service->pruneEmailLogs(30);

    expect($deleted)->toBe(1);
    expect(EmailLog::count())->toBe(1);
    expect(EmailLog::first()->id)->toBe($recentLog->id);
    expect(EmailAttempt::count())->toBe(1);
    expect(EmailAttempt::first()->email_log_id)->toBe($recentLog->id);
});

test('housekeeping service flushes failed jobs', function (): void {
    $service = app(HousekeepingService::class);

    DB::table('failed_jobs')->insert([
        [
            'uuid' => Str::uuid()->toString(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'Test exception 1',
            'failed_at' => now()->subDays(45),
        ],
        [
            'uuid' => Str::uuid()->toString(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'Test exception 2',
            'failed_at' => now()->subDays(10),
        ],
    ]);

    expect(DB::table('failed_jobs')->count())->toBe(2);

    $flushed = $service->flushFailedJobs(30);

    expect($flushed)->toBe(1);
    expect(DB::table('failed_jobs')->count())->toBe(1);

    // Flush all
    $service->flushFailedJobs();
    expect(DB::table('failed_jobs')->count())->toBe(0);
});

test('housekeeping service prunes activity audit logs', function (): void {
    $service = app(HousekeepingService::class);
    $user = User::factory()->create();

    $old = ActivityLog::create([
        'user_id' => $user->id,
        'action' => 'old_action',
    ]);
    DB::table('activity_logs')->where('id', $old->id)->update([
        'created_at' => now()->subDays(50),
        'updated_at' => now()->subDays(50),
    ]);

    $recent = ActivityLog::create([
        'user_id' => $user->id,
        'action' => 'recent_action',
    ]);
    DB::table('activity_logs')->where('id', $recent->id)->update([
        'created_at' => now()->subDays(5),
        'updated_at' => now()->subDays(5),
    ]);

    expect(ActivityLog::count())->toBe(2);

    $deleted = $service->pruneActivityLogs(30);

    expect($deleted)->toBe(1);
    expect(ActivityLog::count())->toBe(1);
    expect(ActivityLog::first()->id)->toBe($recent->id);
});

test('housekeeping service never affects protected business entities', function (): void {
    $service = app(HousekeepingService::class);

    $user = User::factory()->create();
    $shipper = Shipper::factory()->create();
    $shipment = Shipment::factory()->create(['shipper_id' => $shipper->id]);
    Invoice::factory()->create(['shipment_id' => $shipment->id]);

    $userCountBefore = User::count();
    $shipperCountBefore = Shipper::count();
    $shipmentCountBefore = Shipment::count();
    $invoiceCountBefore = Invoice::count();

    $service->pruneAllOldData(0);

    expect(User::count())->toBe($userCountBefore);
    expect(Shipper::count())->toBe($shipperCountBefore);
    expect(Shipment::count())->toBe($shipmentCountBefore);
    expect(Invoice::count())->toBe($invoiceCountBefore);
});

test('system config livewire component triggers housekeeping actions and updates counters', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    $log = EmailLog::query()->create([
        'mailable_class' => 'App\Mail\TestMail',
        'recipient_email' => 'old@example.com',
        'status' => EmailLogStatus::Sent,
    ]);
    DB::table('email_logs')->where('id', $log->id)->update([
        'created_at' => now()->subDays(45),
        'updated_at' => now()->subDays(45),
    ]);

    $component = Volt::test('pages::settings.⚡system-config');
    expect($component->get('housekeeping_stats')['email_logs']['total'])->toBe(1);
    expect($component->get('housekeeping_stats')['email_logs']['older_than_30d'])->toBe(1);

    $component->call('pruneEmailLogs', 30);

    expect(EmailLog::count())->toBe(0);
    expect($component->get('housekeeping_stats')['email_logs']['total'])->toBe(0);
    expect($component->get('housekeeping_stats')['email_logs']['older_than_30d'])->toBe(0);
});

test('system config uses flux confirmation modal to display details before deleting', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    $oldLog = EmailLog::query()->create([
        'mailable_class' => 'App\Mail\TestMail',
        'recipient_email' => 'modal_test@example.com',
        'status' => EmailLogStatus::Sent,
    ]);
    DB::table('email_logs')->where('id', $oldLog->id)->update([
        'created_at' => now()->subDays(45),
        'updated_at' => now()->subDays(45),
    ]);

    $component = Volt::test('pages::settings.⚡system-config');

    // Prompting delete opens the modal and populates details without immediately deleting
    $component->call('promptHousekeeping', 'email_logs', 30);
    expect($component->get('showHousekeepingModal'))->toBeTrue();
    expect($component->get('housekeepingModalTitle'))->toContain('30 Days');
    expect($component->get('housekeepingModalDetails'))->not->toBeEmpty();
    expect(EmailLog::count())->toBe(1);

    // Confirming delete in modal performs deletion and closes modal
    $component->call('executeHousekeepingDelete');
    expect($component->get('showHousekeepingModal'))->toBeFalse();
    expect(EmailLog::count())->toBe(0);
});

test('prune housekeeping artisan command executes successfully', function (): void {
    $log = EmailLog::query()->create([
        'mailable_class' => 'App\Mail\TestMail',
        'recipient_email' => 'old@example.com',
        'status' => EmailLogStatus::Sent,
    ]);
    DB::table('email_logs')->where('id', $log->id)->update([
        'created_at' => now()->subDays(45),
        'updated_at' => now()->subDays(45),
    ]);

    $this->artisan('housekeeping:prune --days=30')
        ->expectsOutputToContain('Housekeeping completed successfully')
        ->assertSuccessful();

    expect(EmailLog::count())->toBe(0);
});
