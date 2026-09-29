<?php

declare(strict_types=1);

use App\Services\HousekeepingService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Title;
use Livewire\Component;
use WireUi\Traits\WireUiActions;

new #[Title('System Configuration')] class extends Component {
    use WireUiActions;

    public string $php_version;

    public string $environment;

    public bool $debug_mode;

    public bool $storage_link_exists;

    public int $pending_migrations = 0;

    public array $folder_perms = [];

    public array $available_seeders = [];

    public array $backups = [];

    public string $last_output = '';

    public string $whatsapp_health = 'unknown';

    public array $storage_stats = [];

    public array $housekeeping_stats = [];

    public bool $showHousekeepingModal = false;

    public string $housekeepingTargetAction = '';

    public ?int $housekeepingTargetDays = null;

    public bool $housekeepingDeleteOnlyRead = false;

    public string $housekeepingModalTitle = '';

    public string $housekeepingModalDescription = '';

    public array $housekeepingModalDetails = [];

    public bool $housekeepingIsDangerous = false;

    public bool $showBackupDeleteModal = false;

    public string $backupPendingDeleteName = '';

    public bool $showClearLogsModal = false;

    public array $target_folders = [
        'storage',
        'storage/app',
        'storage/framework',
        'storage/logs',
        'bootstrap/cache',
    ];

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasRole('super_admin') === true, 403);

        $this->refreshStats();
        $this->discoverSeeders();
        $this->fetchBackups();
    }

    public function discoverSeeders(): void
    {
        $path = database_path('seeders');
        if (File::exists($path)) {
            $files = File::files($path);
            $this->available_seeders = collect($files)
                ->map(fn($file) => $file->getFilenameWithoutExtension())
                ->filter(fn($name) => $name !== 'DatabaseSeeder')
                ->values()
                ->toArray();
        }
    }

    public function fetchBackups(): void
    {
        $path = storage_path('app/backups');
        if (!File::exists($path)) {
            File::makeDirectory($path, 0755, true);
        }

        $files = File::files($path);
        $this->backups = collect($files)
            ->map(function ($file) {
                return [
                    'name' => $file->getFilename(),
                    'size' => number_format($file->getSize() / 1024, 2) . ' KB',
                    'created_at' => date('Y-m-d H:i:s', $file->getMTime()),
                    'timestamp' => $file->getMTime(),
                ];
            })
            ->sortByDesc('timestamp')
            ->values()
            ->toArray();
    }

    public function createBackup(): void
    {
        $filename = 'backup-' . date('Y-m-d-His') . '.sql';
        $path = storage_path('app/backups/' . $filename);

        $conn = config('database.connections.mysql');
        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s %s > %s',
            escapeshellarg($conn['username']),
            escapeshellarg($conn['password']),
            escapeshellarg($conn['host']),
            escapeshellarg($conn['database']),
            escapeshellarg($path)
        );

        $output = [];
        $resultCode = 0;
        exec($command, $output, $resultCode);

        if ($resultCode === 0) {
            $this->fetchBackups();
            $this->dialog()->success(
                title: __('Backup created'),
                description: __('Backup created successfully: :name', ['name' => $filename]),
            );
        } else {
            $this->dialog()->error(
                title: __('Backup failed'),
                description: __('Ensure mysqldump is installed and accessible.'),
            );
        }
    }

    public function restoreBackup(string $filename): void
    {
        $path = storage_path('app/backups/' . $filename);
        if (!File::exists($path)) {
            $this->dialog()->error(
                title: __('Backup not found'),
                description: __('Backup file not found.'),
            );

            return;
        }

        $conn = config('database.connections.mysql');
        $command = sprintf(
            'mysql --user=%s --password=%s --host=%s %s < %s',
            escapeshellarg($conn['username']),
            escapeshellarg($conn['password']),
            escapeshellarg($conn['host']),
            escapeshellarg($conn['database']),
            escapeshellarg($path)
        );

        $output = [];
        $resultCode = 0;
        exec($command, $output, $resultCode);

        if ($resultCode === 0) {
            $this->refreshStats();
            $this->dialog()->success(
                title: __('Backup restored'),
                description: __('Database restored successfully from :name', ['name' => $filename]),
            );
        } else {
            $this->dialog()->error(
                title: __('Restoration failed'),
                description: __('Ensure mysql CLI is accessible.'),
            );
        }
    }

    public function deleteBackup(string $filename): void
    {
        $path = storage_path('app/backups/' . $filename);
        if (File::exists($path)) {
            File::delete($path);
            $this->fetchBackups();
            $this->dialog()->success(
                title: __('Backup deleted'),
                description: __('Backup deleted.'),
            );
        }
    }

    public function downloadBackup(string $filename)
    {
        $path = storage_path('app/backups/' . $filename);
        if (File::exists($path)) {
            return response()->download($path);
        }
    }

    public function refreshStats(): void
    {
        $this->php_version = PHP_VERSION;
        $this->environment = app()->environment();
        $this->debug_mode = config('app.debug');
        $this->storage_link_exists = File::exists(public_path('storage'));

        // Detect pending migrations
        try {
            Artisan::call('migrate:status');
            $output = Artisan::output();
            $rows = explode("\n", trim($output));
            // Filter rows that match "| No" indicating a pending migration
            $this->pending_migrations = count(array_filter($rows, fn($row) => str_contains($row, '| No')));
        } catch (\Exception $e) {
            $this->pending_migrations = 0;
        }

        $this->refreshPermissions();
        $this->calculateStorageStats();
        $this->calculateHousekeepingStats();
    }

    public function calculateHousekeepingStats(): void
    {
        $this->housekeeping_stats = app(HousekeepingService::class)->getSummaryStats();
    }

    public function pruneNotifications(?int $days = null, bool $onlyRead = false): void
    {
        try {
            $deleted = app(HousekeepingService::class)->pruneNotifications($days, $onlyRead);
            $this->refreshStats();

            $description = $days !== null
                ? __(':count notifications older than :days days pruned.', ['count' => $deleted, 'days' => $days])
                : ($onlyRead
                    ? __(':count read notifications pruned.', ['count' => $deleted])
                    : __(':count notifications permanently purged.', ['count' => $deleted]));

            $this->dialog()->success(
                title: __('Notifications Housekeeping'),
                description: $description,
            );
        } catch (\Throwable $e) {
            $this->dialog()->error(
                title: __('Prune Failed'),
                description: $e->getMessage(),
            );
        }
    }

    public function pruneEmailLogs(?int $days = null): void
    {
        try {
            $deleted = app(HousekeepingService::class)->pruneEmailLogs($days);
            $this->refreshStats();

            $description = $days !== null
                ? __(':count email logs older than :days days pruned.', ['count' => $deleted, 'days' => $days])
                : __(':count email logs permanently purged.', ['count' => $deleted]);

            $this->dialog()->success(
                title: __('Email Logs Housekeeping'),
                description: $description,
            );
        } catch (\Throwable $e) {
            $this->dialog()->error(
                title: __('Prune Failed'),
                description: $e->getMessage(),
            );
        }
    }

    public function flushFailedJobs(?int $days = null): void
    {
        try {
            $deleted = app(HousekeepingService::class)->flushFailedJobs($days);
            $this->refreshStats();

            $description = $days !== null
                ? __(':count failed jobs older than :days days removed.', ['count' => $deleted, 'days' => $days])
                : __(':count failed jobs flushed.', ['count' => $deleted]);

            $this->dialog()->success(
                title: __('Failed Jobs Housekeeping'),
                description: $description,
            );
        } catch (\Throwable $e) {
            $this->dialog()->error(
                title: __('Flush Failed'),
                description: $e->getMessage(),
            );
        }
    }

    public function pruneActivityLogs(?int $days = null): void
    {
        try {
            $deleted = app(HousekeepingService::class)->pruneActivityLogs($days);
            $this->refreshStats();

            $description = $days !== null
                ? __(':count activity logs older than :days days pruned.', ['count' => $deleted, 'days' => $days])
                : __(':count activity logs permanently purged.', ['count' => $deleted]);

            $this->dialog()->success(
                title: __('Activity Logs Housekeeping'),
                description: $description,
            );
        } catch (\Throwable $e) {
            $this->dialog()->error(
                title: __('Prune Failed'),
                description: $e->getMessage(),
            );
        }
    }

    public function pruneAllHousekeeping(int $days = 30): void
    {
        try {
            $results = app(HousekeepingService::class)->pruneAllOldData($days);
            $this->refreshStats();

            $total = array_sum($results);

            $this->dialog()->success(
                title: __('Housekeeping Completed'),
                description: __('Pruned :total records older than :days days (Notifications: :n, Email Logs: :e, Failed Jobs: :f, Activity Logs: :a).', [
                    'total' => $total,
                    'days' => $days,
                    'n' => $results['notifications'],
                    'e' => $results['email_logs'],
                    'f' => $results['failed_jobs'],
                    'a' => $results['activity_logs'],
                ]),
            );
        } catch (\Throwable $e) {
            $this->dialog()->error(
                title: __('Housekeeping Failed'),
                description: $e->getMessage(),
            );
        }
    }

    public function promptHousekeeping(string $action, ?int $days = null, bool $onlyRead = false): void
    {
        $this->housekeepingTargetAction = $action;
        $this->housekeepingTargetDays = $days;
        $this->housekeepingDeleteOnlyRead = $onlyRead;
        $this->refreshStats();

        $details = [];
        $this->housekeepingIsDangerous = false;

        switch ($action) {
            case 'notifications':
                if ($onlyRead) {
                    $this->housekeepingModalTitle = __('Delete All Read Notifications');
                    $this->housekeepingModalDescription = __('Permanently delete all database notifications that have already been marked as read.');
                    $details[] = ['label' => __('Category'), 'value' => __('Database Notifications')];
                    $details[] = ['label' => __('Scope'), 'value' => __('Only notifications where read_at is timestamped')];
                    $details[] = ['label' => __('Total in Table'), 'value' => number_format($this->housekeeping_stats['notifications']['total'] ?? 0) . ' ' . __('notifications')];
                    $details[] = ['label' => __('Unread Alerts'), 'value' => __('Unread notifications will remain intact')];
                } elseif ($days !== null) {
                    $this->housekeepingModalTitle = __('Prune Notifications Older than :days Days', ['days' => $days]);
                    $this->housekeepingModalDescription = __('Delete notifications created more than :days days ago.', ['days' => $days]);
                    $details[] = ['label' => __('Category'), 'value' => __('Database Notifications')];
                    $details[] = ['label' => __('Age Threshold'), 'value' => __(':days days (created on or before :date)', [
                        'days' => $days,
                        'date' => now()->subDays($days)->toFormattedDateString(),
                    ])];
                    if ($days === 30) {
                        $details[] = ['label' => __('Matching Records'), 'value' => number_format($this->housekeeping_stats['notifications']['older_than_30d'] ?? 0) . ' ' . __('records eligible')];
                    }
                    $details[] = ['label' => __('Total in Table'), 'value' => number_format($this->housekeeping_stats['notifications']['total'] ?? 0) . ' ' . __('notifications')];
                } else {
                    $this->housekeepingIsDangerous = true;
                    $this->housekeepingModalTitle = __('Purge ALL Database Notifications');
                    $this->housekeepingModalDescription = __('Warning: You are about to permanently delete every database notification.');
                    $details[] = ['label' => __('Category'), 'value' => __('Database Notifications table')];
                    $details[] = ['label' => __('Scope'), 'value' => __('ALL notifications (unread and read)')];
                    $details[] = ['label' => __('Total to Delete'), 'value' => number_format($this->housekeeping_stats['notifications']['total'] ?? 0) . ' ' . __('notifications')];
                }
                break;

            case 'email_logs':
                if ($days !== null) {
                    $this->housekeepingModalTitle = __('Prune Email Logs Older than :days Days', ['days' => $days]);
                    $this->housekeepingModalDescription = __('Delete outgoing email logs and transmission attempts older than :days days.', ['days' => $days]);
                    $details[] = ['label' => __('Category'), 'value' => __('Email Logs & Transmission Attempts')];
                    $details[] = ['label' => __('Age Threshold'), 'value' => __(':days days (created on or before :date)', [
                        'days' => $days,
                        'date' => now()->subDays($days)->toFormattedDateString(),
                    ])];
                    if ($days === 30) {
                        $details[] = ['label' => __('Matching Records'), 'value' => number_format($this->housekeeping_stats['email_logs']['older_than_30d'] ?? 0) . ' ' . __('logs eligible')];
                    }
                    $details[] = ['label' => __('Total Logs in DB'), 'value' => number_format($this->housekeeping_stats['email_logs']['total'] ?? 0) . ' ' . __('logs')];
                    $details[] = ['label' => __('Affected Tables'), 'value' => 'email_logs, email_attempts'];
                } else {
                    $this->housekeepingIsDangerous = true;
                    $this->housekeepingModalTitle = __('Purge ALL Email Logs & Attempts');
                    $this->housekeepingModalDescription = __('Warning: You are about to permanently delete all outgoing email logs and attempt records.');
                    $details[] = ['label' => __('Category'), 'value' => __('Email Logs & Attempts')];
                    $details[] = ['label' => __('Scope'), 'value' => __('ALL historical logs and attempts')];
                    $details[] = ['label' => __('Total to Delete'), 'value' => number_format($this->housekeeping_stats['email_logs']['total'] ?? 0) . ' ' . __('logs')];
                    $details[] = ['label' => __('Affected Tables'), 'value' => 'email_logs, email_attempts'];
                }
                break;

            case 'failed_jobs':
                if ($days !== null) {
                    $this->housekeepingModalTitle = __('Flush Failed Jobs Older than :days Days', ['days' => $days]);
                    $this->housekeepingModalDescription = __('Clear asynchronous queue jobs that failed more than :days days ago.', ['days' => $days]);
                    $details[] = ['label' => __('Category'), 'value' => __('Failed Background Queue Jobs')];
                    $details[] = ['label' => __('Age Threshold'), 'value' => __(':days days (failed on or before :date)', [
                        'days' => $days,
                        'date' => now()->subDays($days)->toFormattedDateString(),
                    ])];
                    if ($days === 30) {
                        $details[] = ['label' => __('Matching Records'), 'value' => number_format($this->housekeeping_stats['failed_jobs']['older_than_30d'] ?? 0) . ' ' . __('jobs eligible')];
                    }
                    $details[] = ['label' => __('Total Failed in DB'), 'value' => number_format($this->housekeeping_stats['failed_jobs']['total'] ?? 0) . ' ' . __('failed jobs')];
                    $details[] = ['label' => __('Affected Table'), 'value' => 'failed_jobs'];
                } else {
                    $this->housekeepingIsDangerous = true;
                    $this->housekeepingModalTitle = __('Flush ALL Failed Queue Jobs');
                    $this->housekeepingModalDescription = __('Clear all accumulated failed queue job records from the database.');
                    $details[] = ['label' => __('Category'), 'value' => __('Failed Queue Jobs')];
                    $details[] = ['label' => __('Scope'), 'value' => __('ALL failed jobs')];
                    $details[] = ['label' => __('Total to Delete'), 'value' => number_format($this->housekeeping_stats['failed_jobs']['total'] ?? 0) . ' ' . __('failed jobs')];
                    $details[] = ['label' => __('Affected Table'), 'value' => 'failed_jobs'];
                }
                break;

            case 'activity_logs':
                if ($days !== null) {
                    $this->housekeepingModalTitle = __('Prune Activity Logs Older than :days Days', ['days' => $days]);
                    $this->housekeepingModalDescription = __('Prune historical audit trail logs created more than :days days ago.', ['days' => $days]);
                    $details[] = ['label' => __('Category'), 'value' => __('Activity Audit Logs')];
                    $details[] = ['label' => __('Age Threshold'), 'value' => __(':days days (created on or before :date)', [
                        'days' => $days,
                        'date' => now()->subDays($days)->toFormattedDateString(),
                    ])];
                    if ($days === 30) {
                        $details[] = ['label' => __('Matching Records'), 'value' => number_format($this->housekeeping_stats['activity_logs']['older_than_30d'] ?? 0) . ' ' . __('logs eligible')];
                    }
                    $details[] = ['label' => __('Total Logs in DB'), 'value' => number_format($this->housekeeping_stats['activity_logs']['total'] ?? 0) . ' ' . __('logs')];
                    $details[] = ['label' => __('Protected Data'), 'value' => __('Active users, shipments, and core business records are untouched')];
                } else {
                    $this->housekeepingIsDangerous = true;
                    $this->housekeepingModalTitle = __('Purge ALL Activity Audit Logs');
                    $this->housekeepingModalDescription = __('Warning: You are about to permanently delete all historical activity audit logs.');
                    $details[] = ['label' => __('Category'), 'value' => __('Activity Audit Logs')];
                    $details[] = ['label' => __('Scope'), 'value' => __('ALL historical activity records')];
                    $details[] = ['label' => __('Total to Delete'), 'value' => number_format($this->housekeeping_stats['activity_logs']['total'] ?? 0) . ' ' . __('logs')];
                    $details[] = ['label' => __('Protected Data'), 'value' => __('Active users, shipments, and core business records are untouched')];
                }
                break;

            case 'all':
                $this->housekeepingIsDangerous = true;
                $this->housekeepingModalTitle = __('Purge All Ephemeral Data Older Than 30 Days');
                $this->housekeepingModalDescription = __('Prune notifications, email logs, failed queue jobs, and activity audit logs older than 30 days.');
                $details[] = ['label' => __('Age Threshold'), 'value' => __('Older than 30 days (created/failed on or before :date)', ['date' => now()->subDays(30)->toFormattedDateString()])];
                $details[] = ['label' => __('Notifications (>30d)'), 'value' => number_format($this->housekeeping_stats['notifications']['older_than_30d'] ?? 0) . ' ' . __('records')];
                $details[] = ['label' => __('Email Logs (>30d)'), 'value' => number_format($this->housekeeping_stats['email_logs']['older_than_30d'] ?? 0) . ' ' . __('records')];
                $details[] = ['label' => __('Failed Jobs (>30d)'), 'value' => number_format($this->housekeeping_stats['failed_jobs']['older_than_30d'] ?? 0) . ' ' . __('records')];
                $details[] = ['label' => __('Activity Logs (>30d)'), 'value' => number_format($this->housekeeping_stats['activity_logs']['older_than_30d'] ?? 0) . ' ' . __('records')];
                $details[] = ['label' => __('Protected Tables'), 'value' => __('Shipments, invoices, users, shippers, wallets, and newsletters will NOT be touched')];
                break;
        }

        $this->housekeepingModalDetails = $details;
        $this->showHousekeepingModal = true;
    }

    public function executeHousekeepingDelete(): void
    {
        $action = $this->housekeepingTargetAction;
        $days = $this->housekeepingTargetDays;
        $onlyRead = $this->housekeepingDeleteOnlyRead;

        $this->showHousekeepingModal = false;

        match ($action) {
            'notifications' => $this->pruneNotifications($days, $onlyRead),
            'email_logs' => $this->pruneEmailLogs($days),
            'failed_jobs' => $this->flushFailedJobs($days),
            'activity_logs' => $this->pruneActivityLogs($days),
            'all' => $this->pruneAllHousekeeping($days ?? 30),
            default => null,
        };
    }

    public function confirmDeleteBackup(string $filename): void
    {
        $this->backupPendingDeleteName = $filename;
        $this->showBackupDeleteModal = true;
    }

    public function executeDeleteBackup(): void
    {
        if ($this->backupPendingDeleteName !== '') {
            $this->deleteBackup($this->backupPendingDeleteName);
            $this->backupPendingDeleteName = '';
            $this->showBackupDeleteModal = false;
        }
    }

    public function confirmClearLogs(): void
    {
        $this->showClearLogsModal = true;
    }

    public function executeClearLogs(): void
    {
        $this->showClearLogsModal = false;
        $this->clearLogs(true);
    }

    public function refreshPermissions(): void
    {
        $this->folder_perms = [];
        foreach ($this->target_folders as $folder) {
            $path = base_path($folder);
            if (File::exists($path)) {
                $this->folder_perms[$folder] = substr(sprintf('%o', fileperms($path)), -4);
            } else {
                $this->folder_perms[$folder] = 'missing';
            }
        }
    }

    public function fixPermission(string $folder): void
    {
        $path = base_path($folder);
        if (!File::exists($path)) {
            $this->dialog()->error(
                title: __('Missing folder'),
                description: __(':folder is missing.', ['folder' => $folder]),
            );

            return;
        }

        try {
            // Attempt to set 775 (0775 octal)
            if (chmod($path, 0775)) {
                $this->refreshPermissions();
                $this->dialog()->success(
                    title: __('Permissions updated'),
                    description: __('Permissions for :folder set to 0775.', ['folder' => $folder]),
                );
            } else {
                throw new \Exception(__('chmod failed.'));
            }
        } catch (\Exception $e) {
            $this->dialog()->error(
                title: __('Permission update failed'),
                description: __('Failed to change permissions for :folder. This might be restricted by your host.', ['folder' => $folder]),
            );
        }
    }

    public function createStorageLink(): void
    {
        try {
            Artisan::call('storage:link');
            $this->last_output = Artisan::output();
            $this->refreshStats();
            $this->dialog()->success(
                title: __('Storage link created'),
                description: __('Storage link created successfully.'),
            );
        } catch (\Exception $e) {
            $this->last_output = $e->getMessage();
            $this->dialog()->error(
                title: __('Storage link failed'),
                description: __('Failed to create storage link: ') . $e->getMessage(),
            );
        }
    }

    public function runMigrations(): void
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            $this->last_output = Artisan::output();
            $this->refreshStats();
            $this->dialog()->success(
                title: __('Migrations completed'),
                description: __('Database migrations executed successfully.'),
            );
        } catch (\Exception $e) {
            $this->last_output = $e->getMessage();
            $this->dialog()->error(
                title: __('Migration failed'),
                description: __('Migration failed: ') . $e->getMessage(),
            );
        }
    }

    public function runSeeder(?string $class = null): void
    {
        try {
            $params = ['--force' => true];
            if ($class) {
                $params['--class'] = $class;
            }
            Artisan::call('db:seed', $params);
            $this->last_output = Artisan::output();
            $this->dialog()->success(
                title: __('Seeding completed'),
                description: $class ? __(':class seeder completed.', ['class' => $class]) : __('All database seeders completed.'),
            );
        } catch (\Exception $e) {
            $this->last_output = $e->getMessage();
            $this->dialog()->error(
                title: __('Seeding failed'),
                description: __('Seeding failed: ') . $e->getMessage(),
            );
        }
    }

    public function restartQueue(): void
    {
        try {
            Artisan::call('queue:restart');
            $this->last_output = Artisan::output();
            $this->dialog()->success(
                title: __('Queue restart signal sent'),
                description: __('Queue workers will restart after completing their current job.'),
            );
        } catch (\Exception $e) {
            $this->last_output = $e->getMessage();
            $this->dialog()->error(
                title: __('Queue restart failed'),
                description: __('Failed to send restart signal.'),
            );
        }
    }

    public function clearOptimization(): void
    {
        try {
            Artisan::call('optimize:clear');
            $this->last_output = Artisan::output();

            session()->flash('toast', [
                'type' => 'success',
                'message' => __('Optimization cache cleared.'),
            ]);

            $this->redirect(route('system-config.edit'));
        } catch (\Exception $e) {
            $this->last_output = $e->getMessage();
            $this->dialog()->error(
                title: __('Optimization clear failed'),
                description: __('Failed to clear optimization cache.'),
            );
        }
    }

    public function checkWhatsAppHealth(): void
    {
        try {
            $token = config('whatsapp.token');
            $id = config('whatsapp.phone_number_id');

            if (!$token || !$id) {
                $this->whatsapp_health = 'error';
                $this->dialog()->error(__('WhatsApp credentials missing in .env'));
                return;
            }

            $response = Http::withToken($token)->get("https://graph.facebook.com/v19.0/{$id}");

            if ($response->successful()) {
                $this->whatsapp_health = 'healthy';
                $this->dialog()->success(__('WhatsApp API connection is healthy'));
            } else {
                $this->whatsapp_health = 'error';
                $this->dialog()->error(__('WhatsApp API error: ') . ($response->json('error.message') ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            $this->whatsapp_health = 'error';
            $this->dialog()->error(__('WhatsApp connection failed'));
        }
    }

    public function clearLogs(bool $confirmed = false): void
    {
        if (! $confirmed) {
            $this->dialog()->confirm([
                'title' => __('Are you sure?'),
                'description' => __('This will permanently truncate all system log files.'),
                'icon' => 'warning',
                'accept' => [
                    'label' => __('Yes, clear them'),
                    'method' => 'clearLogs',
                    'params' => true,
                ],
                'reject' => [
                    'label' => __('Cancel'),
                ],
            ]);

            return;
        }

        try {
            $logs = ['laravel.log', 'whatsapp.log', 'queue.log'];
            foreach ($logs as $log) {
                $path = storage_path("logs/{$log}");
                if (File::exists($path)) {
                    File::put($path, '');
                }
            }
            $this->dialog()->success(__('All logs cleared successfully'));
            $this->calculateStorageStats();
        } catch (\Exception $e) {
            $this->dialog()->error(__('Failed to clear logs'));
        }
    }

    public function calculateStorageStats(): void
    {
        $this->storage_stats = [
            'total' => $this->getDirSize(storage_path()),
            'logs' => $this->getDirSize(storage_path('logs')),
            'app' => $this->getDirSize(storage_path('app')),
        ];
    }

    private function getDirSize(string $path): string
    {
        if (!File::exists($path)) {
            return '0 B';
        }
        $size = 0;
        foreach (File::allFiles($path) as $file) {
            $size += $file->getSize();
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $size > 1024; $i++) {
            $size /= 1024;
        }
        return round($size, 2) . ' ' . $units[$i];
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('System Configuration') }}</flux:heading>

    <x-pages::settings.layout :heading="__('System Configuration')" :subheading="__('Manage production environment and system-wide maintenance tasks.')">
        <div class="space-y-6">
            <!-- Environment Stats -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:card
                    class="p-4 flex flex-col justify-center items-center text-center space-y-2 border-zinc-100 dark:border-zinc-800">
                    <flux:text size="xs" class="uppercase tracking-widest text-zinc-400 font-bold">
                        {{ __('PHP Version') }}</flux:text>
                    <flux:heading size="xl" class="font-mono">{{ $php_version }}</flux:heading>
                </flux:card>

                <flux:card
                    class="p-4 flex flex-col justify-center items-center text-center space-y-2 border-zinc-100 dark:border-zinc-800">
                    <flux:text size="xs" class="uppercase tracking-widest text-zinc-400 font-bold">
                        {{ __('Environment') }}</flux:text>
                    <div class="flex items-center gap-2">
                        <flux:badge size="sm" :color="$environment === 'production' ? 'green' : 'blue'">
                            {{ ucfirst($environment) }}</flux:badge>
                        @if($debug_mode)
                            <flux:badge size="sm" color="orange" inset="top bottom">{{ __('Debug Active') }}</flux:badge>
                        @endif
                    </div>
                </flux:card>
            </div>

            <!-- New Quick Stats -->
            <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
                <flux:card class="p-4 space-y-2 border-zinc-100 dark:border-zinc-800 col-span-1">
                    <flux:text size="xs" class="uppercase tracking-widest text-zinc-400 font-bold">{{ __('Storage Total') }}</flux:text>
                    <flux:heading size="lg">{{ $storage_stats['total'] ?? '---' }}</flux:heading>
                </flux:card>
                <flux:card class="p-4 space-y-2 border-zinc-100 dark:border-zinc-800 col-span-1">
                    <flux:text size="xs" class="uppercase tracking-widest text-zinc-400 font-bold">{{ __('Log Files') }}</flux:text>
                    <flux:heading size="lg">{{ $storage_stats['logs'] ?? '---' }}</flux:heading>
                </flux:card>
                <flux:card class="p-4 space-y-2 border-zinc-100 dark:border-zinc-800 col-span-2 lg:col-span-1">
                    <flux:text size="xs" class="uppercase tracking-widest text-zinc-400 font-bold">{{ __('WhatsApp Bot') }}</flux:text>
                    <div class="flex items-center justify-between gap-2">
                        <flux:badge size="sm" :color="$whatsapp_health === 'healthy' ? 'green' : ($whatsapp_health === 'error' ? 'red' : 'zinc')">
                            {{ ucfirst($whatsapp_health) }}
                        </flux:badge>
                        <flux:button wire:click="checkWhatsAppHealth" size="xs" icon="arrow-path" variant="ghost" />
                    </div>
                </flux:card>
            </div>

            <!-- Detailed Status Indicators -->
            <flux:card class="space-y-4 border-zinc-100 dark:border-zinc-800">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between py-2 border-b border-zinc-50 dark:border-zinc-800/50 gap-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="p-2 rounded-lg bg-zinc-50 dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800">
                            <flux:icon.link class="size-4 text-zinc-400" />
                        </div>
                        <div>
                            <flux:heading size="sm">{{ __('Storage Link') }}</flux:heading>
                            <flux:text size="xs">{{ __('Requirement for public file accessibility.') }}</flux:text>
                        </div>
                    </div>
                    <div class="flex items-center justify-between sm:justify-end gap-3">
                        <flux:badge size="sm" :color="$storage_link_exists ? 'green' : 'red'" inset="top bottom">
                            {{ $storage_link_exists ? __('Healthy') : __('Broken/Missing') }}
                        </flux:badge>
                        @if(!$storage_link_exists)
                            <flux:button wire:click="createStorageLink" size="xs" variant="primary">{{ __('Fix Now') }}
                            </flux:button>
                        @endif
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center justify-between py-2 gap-4">
                    <div class="flex items-center gap-3">
                        <div
                            class="p-2 rounded-lg bg-zinc-50 dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800">
                            <flux:icon.table-cells class="size-4 text-zinc-400" />
                        </div>
                        <div>
                            <flux:heading size="sm">{{ __('Database Migrations') }}</flux:heading>
                            <flux:text size="xs">{{ __('Consistency between code and database structure.') }}
                            </flux:text>
                        </div>
                    </div>
                    <div class="flex items-center justify-between sm:justify-end gap-3">
                        @if($pending_migrations > 0)
                            <flux:badge size="sm" color="orange" class="font-bold">{{ $pending_migrations }}
                                {{ __('Pending') }}</flux:badge>
                            <flux:button wire:click="runMigrations" size="xs" variant="primary" icon="play">
                                {{ __('Upgrade') }}</flux:button>
                        @else
                            <flux:badge size="sm" color="green" inset="top bottom">{{ __('Up to date') }}</flux:badge>
                        @endif
                    </div>
                </div>
            </flux:card>

            <!-- Directory Permissions -->
            <div class="space-y-4">
                <flux:heading size="sm" weight="semibold" class="uppercase tracking-wider text-zinc-400">
                    {{ __('Directory Permissions') }}</flux:heading>
                <flux:card
                    class="divide-y divide-zinc-100 dark:divide-zinc-800 p-0 border-zinc-100 dark:border-zinc-800 overflow-hidden">
                    @foreach($target_folders as $folder)
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between p-4 bg-white dark:bg-zinc-900/50 gap-4">
                            <div class="flex items-center gap-3">
                                <div
                                    class="p-2 rounded-lg bg-zinc-50 dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800">
                                    <flux:icon.folder class="size-4 text-zinc-400" />
                                </div>
                                <div class="overflow-hidden">
                                    <flux:heading size="sm" class="font-mono text-xs truncate">{{ $folder }}</flux:heading>
                                    <flux:text size="xs">{{ __('Current: ') }} <span
                                            class="font-mono font-bold @if($folder_perms[$folder] === '0775' || $folder_perms[$folder] === '0755') text-green-600 @else text-orange-600 @endif">{{ $folder_perms[$folder] ?? '---' }}</span>
                                    </flux:text>
                                </div>
                            </div>
                            <div class="flex items-center justify-end gap-2 shrink-0">
                                @if($folder_perms[$folder] !== '0775' && $folder_perms[$folder] !== 'missing')
                                    <flux:button wire:click="fixPermission('{{ $folder }}')" size="xs" variant="subtle"
                                        icon="wrench-screwdriver" class="text-[10px]">{{ __('Fix (775)') }}</flux:button>
                                @elseif($folder_perms[$folder] === 'missing')
                                    <flux:badge size="sm" color="red" inset="top bottom">{{ __('Missing') }}</flux:badge>
                                @else
                                    <flux:badge size="sm" color="green" icon="check" inset="top bottom">{{ __('Optimal') }}
                                    </flux:badge>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </flux:card>
            </div>

            <!-- Maintenance & Seeders -->
            <div class="space-y-4">
                <flux:heading size="sm" weight="semibold" class="uppercase tracking-wider text-zinc-400">
                    {{ __('Maintenance & Seeding') }}</flux:heading>

                <div class="grid grid-cols-1 gap-4">
                    <flux:card class="p-4 border-zinc-100 dark:border-zinc-800">
                        <div class="flex flex-col lg:flex-row lg:items-start gap-4">
                            <div class="flex items-start gap-3 flex-1">
                                <flux:icon.document-text class="size-5 text-zinc-500 mt-1" />
                                <div class="flex-1">
                                    <flux:heading size="sm">{{ __('Log Management') }}</flux:heading>
                                    <flux:text size="xs">
                                        {{ __('Clear laravel.log, whatsapp.log, and queue.log to free up disk space.') }}
                                    </flux:text>
                                </div>
                            </div>
                            <flux:button wire:click="confirmClearLogs" size="sm" variant="subtle" class="w-full lg:w-auto">
                                {{ __('Truncate All Logs') }}</flux:button>
                        </div>
                    </flux:card>

                    <flux:card class="p-4 border-zinc-100 dark:border-zinc-800">
                        <div class="flex flex-col lg:flex-row lg:items-start gap-4">
                            <div class="flex items-start gap-3 flex-1">
                                <flux:icon.arrow-path class="size-5 text-orange-500 mt-1" />
                                <div class="flex-1">
                                    <flux:heading size="sm">{{ __('Queue Management') }}</flux:heading>
                                    <flux:text size="xs">
                                        {{ __('Send a restart signal to the background workers. Use this after code or permission updates.') }}
                                    </flux:text>
                                </div>
                            </div>
                            <flux:button wire:click="restartQueue" size="sm" variant="subtle" class="w-full lg:w-auto">
                                {{ __('Restart Queue') }}</flux:button>
                        </div>
                    </flux:card>

                    <flux:card class="p-4 border-zinc-100 dark:border-zinc-800">
                        <div class="flex flex-col lg:flex-row lg:items-start gap-4">
                            <div class="flex items-start gap-3 flex-1">
                                <flux:icon.sparkles class="size-5 text-blue-500 mt-1" />
                                <div class="flex-1">
                                    <flux:heading size="sm">{{ __('Optimization') }}</flux:heading>
                                    <flux:text size="xs">
                                        {{ __('Clear configuration, routing, and application caches to reflect recent changes.') }}
                                    </flux:text>
                                </div>
                            </div>
                            <flux:button wire:click="clearOptimization" size="sm" variant="subtle" class="w-full lg:w-auto">
                                {{ __('Clear All Caches') }}</flux:button>
                        </div>
                    </flux:card>

                    <flux:card class="p-4 border-zinc-100 dark:border-zinc-800">
                        <div class="flex flex-col lg:flex-row lg:items-start gap-4 border-b border-zinc-50 dark:border-zinc-800 pb-4">
                            <div class="flex items-start gap-3 flex-1">
                                <flux:icon.beaker class="size-5 text-purple-500 mt-1" />
                                <div class="flex-1">
                                    <flux:heading size="sm">{{ __('Database Seeding') }}</flux:heading>
                                    <flux:text size="xs">{{ __('Populate the database with initial or dummy data.') }}
                                    </flux:text>
                                </div>
                            </div>
                            <flux:button wire:click="runSeeder()" size="sm" variant="subtle" icon="play-circle" class="w-full lg:w-auto">
                                {{ __('Run Base Seeds') }}</flux:button>
                        </div>

                        <div class="space-y-3">
                            <flux:text size="xs" weight="medium" class="text-zinc-500 uppercase tracking-wider pt-4">
                                {{ __('Selective Seeders') }}</flux:text>
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                                @foreach($available_seeders as $seeder)
                                    <div
                                        class="flex items-center justify-between p-2 rounded-lg bg-zinc-50 dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800">
                                        <flux:text size="xs" class="font-mono truncate mr-2">{{ $seeder }}</flux:text>
                                        <flux:button wire:click="runSeeder('{{ $seeder }}')" size="xs" variant="ghost"
                                            icon="play" class="shrink-0" />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </flux:card>
                </div>
            </div>

            <!-- Database Housekeeping & Data Retention -->
            <div class="space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <flux:heading size="sm" weight="semibold" class="uppercase tracking-wider text-zinc-400">
                            {{ __('Database Housekeeping & Retention') }}
                        </flux:heading>
                        <flux:text size="xs" class="text-zinc-500">
                            {{ __('Safely prune accumulated ephemeral data to optimize database performance and disk usage.') }}
                        </flux:text>
                    </div>

                    <flux:button wire:click="promptHousekeeping('all', 30)"
                        size="xs" variant="primary" icon="trash">
                        {{ __('Purge All Older than 30 Days') }}
                    </flux:button>
                </div>

                <!-- Master Card with 4 Differentiated Sub-Cards in Single Column -->
                <flux:card class="p-4 sm:p-5 border-zinc-100 dark:border-zinc-800 space-y-4">
                    <!-- 1. Notifications Sub-Card -->
                    <div class="p-4 sm:p-4.5 rounded-xl bg-zinc-50/70 dark:bg-zinc-900/60 border border-zinc-200/80 dark:border-zinc-800 space-y-3.5 transition-colors hover:border-zinc-300 dark:hover:border-zinc-700">
                        <div class="flex items-start sm:items-center gap-3.5">
                            <div class="p-2.5 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-100 dark:border-amber-900/50 shrink-0">
                                <flux:icon.bell class="size-5 text-amber-500" />
                            </div>
                            <div class="space-y-0.5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <flux:heading size="sm" class="font-bold">{{ __('Notifications') }}</flux:heading>
                                    <flux:badge size="xs" color="amber" variant="subtle">
                                        {{ __(':count total', ['count' => number_format($housekeeping_stats['notifications']['total'] ?? 0)]) }}
                                    </flux:badge>
                                    @if(($housekeeping_stats['notifications']['older_than_30d'] ?? 0) > 0)
                                        <flux:badge size="xs" color="zinc" variant="outline">
                                            {{ __(':count older than 30d', ['count' => number_format($housekeeping_stats['notifications']['older_than_30d'] ?? 0)]) }}
                                        </flux:badge>
                                    @endif
                                </div>
                                <flux:text size="xs" class="text-zinc-500">
                                    {{ __('Database system and user activity alerts.') }}
                                </flux:text>
                            </div>
                        </div>

                        <!-- Action Buttons at bottom with demarcation line -->
                        <div class="pt-3 border-t border-zinc-200/80 dark:border-zinc-800 flex flex-wrap items-center gap-2">
                            <flux:button wire:click="promptHousekeeping('notifications', 30)"
                                size="xs" variant="subtle">
                                {{ __('> 30 Days') }}
                            </flux:button>
                            <flux:button wire:click="promptHousekeeping('notifications', 90)"
                                size="xs" variant="subtle">
                                {{ __('> 90 Days') }}
                            </flux:button>
                            <flux:button wire:click="promptHousekeeping('notifications', null, true)"
                                size="xs" variant="subtle">
                                {{ __('All Read') }}
                            </flux:button>
                            <flux:button wire:click="promptHousekeeping('notifications', null)"
                                size="xs" variant="ghost" color="red">
                                {{ __('Purge All') }}
                            </flux:button>
                        </div>
                    </div>

                    <!-- 2. Email Logs & Attempts Sub-Card -->
                    <div class="p-4 sm:p-4.5 rounded-xl bg-zinc-50/70 dark:bg-zinc-900/60 border border-zinc-200/80 dark:border-zinc-800 space-y-3.5 transition-colors hover:border-zinc-300 dark:hover:border-zinc-700">
                        <div class="flex items-start sm:items-center gap-3.5">
                            <div class="p-2.5 rounded-lg bg-blue-50 dark:bg-blue-950/40 border border-blue-100 dark:border-blue-900/50 shrink-0">
                                <flux:icon.envelope class="size-5 text-blue-500" />
                            </div>
                            <div class="space-y-0.5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <flux:heading size="sm" class="font-bold">{{ __('Email Logs & Attempts') }}</flux:heading>
                                    <flux:badge size="xs" color="blue" variant="subtle">
                                        {{ __(':count total', ['count' => number_format($housekeeping_stats['email_logs']['total'] ?? 0)]) }}
                                    </flux:badge>
                                    @if(($housekeeping_stats['email_logs']['older_than_30d'] ?? 0) > 0)
                                        <flux:badge size="xs" color="zinc" variant="outline">
                                            {{ __(':count older than 30d', ['count' => number_format($housekeeping_stats['email_logs']['older_than_30d'] ?? 0)]) }}
                                        </flux:badge>
                                    @endif
                                </div>
                                <flux:text size="xs" class="text-zinc-500">
                                    {{ __('Outgoing email bodies, delivery statuses, and SMTP transmission attempts.') }}
                                </flux:text>
                            </div>
                        </div>

                        <!-- Action Buttons at bottom with demarcation line -->
                        <div class="pt-3 border-t border-zinc-200/80 dark:border-zinc-800 flex flex-wrap items-center gap-2">
                            <flux:button wire:click="promptHousekeeping('email_logs', 30)"
                                size="xs" variant="subtle">
                                {{ __('> 30 Days') }}
                            </flux:button>
                            <flux:button wire:click="promptHousekeeping('email_logs', 90)"
                                size="xs" variant="subtle">
                                {{ __('> 90 Days') }}
                            </flux:button>
                            <flux:button wire:click="promptHousekeeping('email_logs', null)"
                                size="xs" variant="ghost" color="red">
                                {{ __('Purge All') }}
                            </flux:button>
                        </div>
                    </div>

                    <!-- 3. Failed Queue Jobs Sub-Card -->
                    <div class="p-4 sm:p-4.5 rounded-xl bg-zinc-50/70 dark:bg-zinc-900/60 border border-zinc-200/80 dark:border-zinc-800 space-y-3.5 transition-colors hover:border-zinc-300 dark:hover:border-zinc-700">
                        <div class="flex items-start sm:items-center gap-3.5">
                            <div class="p-2.5 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-100 dark:border-rose-900/50 shrink-0">
                                <flux:icon.exclamation-circle class="size-5 text-rose-500" />
                            </div>
                            <div class="space-y-0.5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <flux:heading size="sm" class="font-bold">{{ __('Failed Queue Jobs') }}</flux:heading>
                                    <flux:badge size="xs" :color="($housekeeping_stats['failed_jobs']['total'] ?? 0) > 0 ? 'rose' : 'zinc'" variant="subtle">
                                        {{ __(':count failed', ['count' => number_format($housekeeping_stats['failed_jobs']['total'] ?? 0)]) }}
                                    </flux:badge>
                                    @if(($housekeeping_stats['failed_jobs']['older_than_30d'] ?? 0) > 0)
                                        <flux:badge size="xs" color="zinc" variant="outline">
                                            {{ __(':count older than 30d', ['count' => number_format($housekeeping_stats['failed_jobs']['older_than_30d'] ?? 0)]) }}
                                        </flux:badge>
                                    @endif
                                </div>
                                <flux:text size="xs" class="text-zinc-500">
                                    {{ __('Unprocessed or errored asynchronous background jobs.') }}
                                </flux:text>
                            </div>
                        </div>

                        <!-- Action Buttons at bottom with demarcation line -->
                        <div class="pt-3 border-t border-zinc-200/80 dark:border-zinc-800 flex flex-wrap items-center gap-2">
                            <flux:button wire:click="promptHousekeeping('failed_jobs', 30)"
                                size="xs" variant="subtle">
                                {{ __('> 30 Days') }}
                            </flux:button>
                            <flux:button wire:click="promptHousekeeping('failed_jobs', null)"
                                size="xs" variant="ghost" color="red">
                                {{ __('Flush All Failed Jobs') }}
                            </flux:button>
                        </div>
                    </div>

                    <!-- 4. Activity Audit Logs Sub-Card -->
                    <div class="p-4 sm:p-4.5 rounded-xl bg-zinc-50/70 dark:bg-zinc-900/60 border border-zinc-200/80 dark:border-zinc-800 space-y-3.5 transition-colors hover:border-zinc-300 dark:hover:border-zinc-700">
                        <div class="flex items-start sm:items-center gap-3.5">
                            <div class="p-2.5 rounded-lg bg-purple-50 dark:bg-purple-950/40 border border-purple-100 dark:border-purple-900/50 shrink-0">
                                <flux:icon.clock class="size-5 text-purple-500" />
                            </div>
                            <div class="space-y-0.5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <flux:heading size="sm" class="font-bold">{{ __('Activity Audit Logs') }}</flux:heading>
                                    <flux:badge size="xs" color="purple" variant="subtle">
                                        {{ __(':count total', ['count' => number_format($housekeeping_stats['activity_logs']['total'] ?? 0)]) }}
                                    </flux:badge>
                                    @if(($housekeeping_stats['activity_logs']['older_than_30d'] ?? 0) > 0)
                                        <flux:badge size="xs" color="zinc" variant="outline">
                                            {{ __(':count older than 30d', ['count' => number_format($housekeeping_stats['activity_logs']['older_than_30d'] ?? 0)]) }}
                                        </flux:badge>
                                    @endif
                                </div>
                                <flux:text size="xs" class="text-zinc-500">
                                    {{ __('Historical user action and shipment event audit logs.') }}
                                </flux:text>
                            </div>
                        </div>

                        <!-- Action Buttons at bottom with demarcation line -->
                        <div class="pt-3 border-t border-zinc-200/80 dark:border-zinc-800 flex flex-wrap items-center gap-2">
                            <flux:button wire:click="promptHousekeeping('activity_logs', 30)"
                                size="xs" variant="subtle">
                                {{ __('> 30 Days') }}
                            </flux:button>
                            <flux:button wire:click="promptHousekeeping('activity_logs', 90)"
                                size="xs" variant="subtle">
                                {{ __('> 90 Days') }}
                            </flux:button>
                            <flux:button wire:click="promptHousekeeping('activity_logs', null)"
                                size="xs" variant="ghost" color="red">
                                {{ __('Purge All') }}
                            </flux:button>
                        </div>
                    </div>
                </flux:card>
            </div>

            <!-- Data Backups -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="sm" weight="semibold" class="uppercase tracking-wider text-zinc-400">
                        {{ __('Data Backups') }}</flux:heading>
                    <flux:button wire:click="createBackup" size="xs" variant="primary" icon="plus">
                        {{ __('Create New Backup') }}</flux:button>
                </div>

                <flux:card class="p-0 border-zinc-100 dark:border-zinc-800 overflow-hidden">
                    <flux:table>
                        <flux:table.columns sticky class="bg-white dark:bg-zinc-900">
                            <flux:table.column>{{ __('Filename') }}</flux:table.column>
                            <flux:table.column>{{ __('Size') }}</flux:table.column>
                            <flux:table.column>{{ __('Created At') }}</flux:table.column>
                            <flux:table.column></flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @forelse($backups as $backup)
                                <flux:table.row :key="$backup['name']">
                                    <flux:table.cell class="font-mono text-xs">{{ $backup['name'] }}</flux:table.cell>
                                    <flux:table.cell>{{ $backup['size'] }}</flux:table.cell>
                                    <flux:table.cell>{{ $backup['created_at'] }}</flux:table.cell>
                                    <flux:table.cell>
                                        <div class="flex items-center justify-end gap-2">
                                            <flux:button wire:click="downloadBackup('{{ $backup['name'] }}')" size="xs"
                                                variant="ghost" icon="arrow-down-tray" tooltip="{{ __('Download') }}" />

                                            <flux:modal.trigger
                                                name="confirm-restore-{{ str_replace('.', '-', $backup['name']) }}">
                                                <flux:button size="xs" variant="subtle" icon="arrow-path"
                                                    tooltip="{{ __('Restore') }}" />
                                            </flux:modal.trigger>

                                            <flux:button wire:click="confirmDeleteBackup('{{ $backup['name'] }}')"
                                                size="xs" variant="ghost" icon="trash" color="red"
                                                tooltip="{{ __('Delete') }}" />

                                            <flux:modal name="confirm-restore-{{ str_replace('.', '-', $backup['name']) }}"
                                                class="min-w-[22rem] space-y-6">
                                                <div class="space-y-2">
                                                    <flux:heading size="lg">{{ __('Confirm Restoration') }}</flux:heading>
                                                    <flux:subheading>
                                                        {{ __('Are you sure you want to restore the database from :name? This will overwrite all current data.', ['name' => $backup['name']]) }}
                                                    </flux:subheading>
                                                </div>

                                                <div class="flex gap-2">
                                                    <flux:spacer />
                                                    <flux:modal.close>
                                                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                                                    </flux:modal.close>
                                                    <flux:button wire:click="restoreBackup('{{ $backup['name'] }}')"
                                                        variant="danger">{{ __('Yes, Restore Now') }}</flux:button>
                                                </div>
                                            </flux:modal>
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="4" class="text-center py-8 text-zinc-400">
                                        {{ __('No backups found.') }}
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </flux:card>
            </div>



            <!-- Terminal Output (Optional/Last Action) -->
            @if($last_output)
                <div class="mt-8 space-y-2">
                    <div class="flex items-center justify-between">
                        <flux:text size="xs" class="uppercase tracking-widest text-zinc-400 font-bold">
                            {{ __('Last Command Output') }}</flux:text>
                        <flux:button variant="ghost" size="xs" wire:click="$set('last_output', '')">{{ __('Clear Output') }}
                        </flux:button>
                    </div>
                    <pre
                        class="p-4 bg-zinc-900 text-zinc-300 rounded-xl font-mono text-[10px] overflow-x-auto whitespace-pre-wrap leading-relaxed shadow-inner">{{ $last_output }}</pre>
                </div>
            @endif

            <!-- Warning for Shared Hosting -->
            <div
                class="p-4 rounded-xl bg-orange-50 dark:bg-orange-950/20 border border-orange-100 dark:border-orange-900/50 flex gap-3">
                <flux:icon.exclamation-triangle class="size-5 text-orange-600 dark:text-orange-500 shrink-0 mt-0.5" />
                <div>
                    <flux:text size="xs" class="text-orange-800 dark:text-orange-300 font-bold">
                        {{ __('Shared Hosting Tip') }}</flux:text>
                    <flux:text size="xs" class="text-orange-700 dark:text-orange-400 mt-1">
                        {{ __('Some systems may restrict symlink creation or CLI execution. If "Fix Now" fails, you might need to contact support to create the storage link manually.') }}
                    </flux:text>
                </div>
            </div>

            <!-- Flux Confirmation Modals -->
            <!-- 1. Housekeeping Confirmation Delete Modal -->
            <flux:modal wire:model="showHousekeepingModal" class="max-w-lg">
                <div class="space-y-5">
                    <div class="flex items-start gap-3.5">
                        <div @class([
                            'p-2.5 rounded-xl border shrink-0',
                            'bg-red-50 dark:bg-red-950/40 border-red-200 dark:border-red-900/60 text-red-600 dark:text-red-400' => $housekeepingIsDangerous,
                            'bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-900/60 text-amber-600 dark:text-amber-400' => !$housekeepingIsDangerous,
                        ])>
                            <flux:icon.trash class="size-6" />
                        </div>
                        <div class="flex-1">
                            <flux:heading size="lg" class="font-bold">{{ $housekeepingModalTitle }}</flux:heading>
                            <flux:subheading class="mt-1 text-zinc-500 dark:text-zinc-400">
                                {{ $housekeepingModalDescription }}
                            </flux:subheading>
                        </div>
                    </div>

                    @if(!empty($housekeepingModalDetails))
                        <div class="rounded-xl border border-zinc-200/80 dark:border-zinc-800 bg-zinc-50/80 dark:bg-zinc-900/70 p-4 space-y-2 text-xs">
                            <div class="text-[11px] font-bold uppercase tracking-wider text-zinc-400 dark:text-zinc-500 mb-2">
                                {{ __('Details of items to be deleted') }}
                            </div>
                            @foreach($housekeepingModalDetails as $detail)
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 py-1.5 border-b border-zinc-200/60 dark:border-zinc-800/80 last:border-0">
                                    <span class="font-medium text-zinc-500 dark:text-zinc-400">{{ $detail['label'] }}</span>
                                    <span class="font-semibold text-zinc-900 dark:text-zinc-100 sm:text-right">{{ $detail['value'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($housekeepingIsDangerous)
                        <div class="p-3 rounded-lg bg-red-50/80 dark:bg-red-950/30 border border-red-200 dark:border-red-900/60 flex items-center gap-2.5 text-xs text-red-700 dark:text-red-300">
                            <flux:icon.exclamation-triangle class="size-4 shrink-0 text-red-600 dark:text-red-400" />
                            <span>{{ __('Warning: This operation permanently removes data and cannot be undone.') }}</span>
                        </div>
                    @endif

                    <div class="flex items-center justify-end gap-2.5 pt-2">
                        <flux:modal.close>
                            <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button variant="danger" wire:click="executeHousekeepingDelete" wire:loading.attr="disabled">
                            {{ __('Confirm Delete') }}
                        </flux:button>
                    </div>
                </div>
            </flux:modal>

            <!-- 2. Backup Archive Delete Modal -->
            <flux:modal wire:model="showBackupDeleteModal" class="max-w-md">
                <div class="space-y-5">
                    <div class="flex items-start gap-3.5">
                        <div class="p-2.5 rounded-xl border shrink-0 bg-red-50 dark:bg-red-950/40 border-red-200 dark:border-red-900/60 text-red-600 dark:text-red-400">
                            <flux:icon.trash class="size-6" />
                        </div>
                        <div class="flex-1">
                            <flux:heading size="lg" class="font-bold">{{ __('Delete Backup Archive?') }}</flux:heading>
                            <flux:subheading class="mt-1 text-zinc-500 dark:text-zinc-400">
                                {{ __('Are you sure you want to permanently delete this database backup? This file cannot be recovered.') }}
                            </flux:subheading>
                        </div>
                    </div>

                    @if($backupPendingDeleteName !== '')
                        <div class="rounded-xl border border-zinc-200/80 dark:border-zinc-800 bg-zinc-50/80 dark:bg-zinc-900/70 p-3.5 space-y-2 text-xs">
                            <div class="text-[11px] font-bold uppercase tracking-wider text-zinc-400 dark:text-zinc-500 mb-1">
                                {{ __('Backup file details') }}
                            </div>
                            <div class="flex items-center justify-between py-1 border-b border-zinc-200/60 dark:border-zinc-800/80">
                                <span class="text-zinc-500 dark:text-zinc-400">{{ __('Filename') }}</span>
                                <span class="font-mono font-semibold text-zinc-900 dark:text-zinc-100 text-xs">{{ $backupPendingDeleteName }}</span>
                            </div>
                            <div class="flex items-center justify-between py-1">
                                <span class="text-zinc-500 dark:text-zinc-400">{{ __('Location') }}</span>
                                <span class="font-mono text-zinc-600 dark:text-zinc-300 text-xs">storage/app/backups/</span>
                            </div>
                        </div>
                    @endif

                    <div class="flex items-center justify-end gap-2.5 pt-2">
                        <flux:modal.close>
                            <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button variant="danger" wire:click="executeDeleteBackup" wire:loading.attr="disabled">
                            {{ __('Delete Backup') }}
                        </flux:button>
                    </div>
                </div>
            </flux:modal>

            <!-- 3. Truncate Logs Modal -->
            <flux:modal wire:model="showClearLogsModal" class="max-w-md">
                <div class="space-y-5">
                    <div class="flex items-start gap-3.5">
                        <div class="p-2.5 rounded-lg border shrink-0 bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-900/60 text-amber-600 dark:text-amber-400">
                            <flux:icon.document-text class="size-6" />
                        </div>
                        <div class="flex-1">
                            <flux:heading size="lg" class="font-bold">{{ __('Truncate System Logs?') }}</flux:heading>
                            <flux:subheading class="mt-1 text-zinc-500 dark:text-zinc-400">
                                {{ __('This will permanently empty all system log files to free up disk storage.') }}
                            </flux:subheading>
                        </div>
                    </div>

                    <div class="rounded-xl border border-zinc-200/80 dark:border-zinc-800 bg-zinc-50/80 dark:bg-zinc-900/70 p-3.5 space-y-2 text-xs">
                        <div class="text-[11px] font-bold uppercase tracking-wider text-zinc-400 dark:text-zinc-500 mb-1">
                            {{ __('Log files to truncate') }}
                        </div>
                        <div class="flex items-center justify-between py-1 border-b border-zinc-200/60 dark:border-zinc-800/80">
                            <span class="text-zinc-500 dark:text-zinc-400 font-mono">laravel.log</span>
                            <span class="text-zinc-600 dark:text-zinc-300">Framework & App Exceptions</span>
                        </div>
                        <div class="flex items-center justify-between py-1 border-b border-zinc-200/60 dark:border-zinc-800/80">
                            <span class="text-zinc-500 dark:text-zinc-400 font-mono">whatsapp.log</span>
                            <span class="text-zinc-600 dark:text-zinc-300">WhatsApp Webhook Events</span>
                        </div>
                        <div class="flex items-center justify-between py-1">
                            <span class="text-zinc-500 dark:text-zinc-400 font-mono">queue.log</span>
                            <span class="text-zinc-600 dark:text-zinc-300">Queue Worker Processing</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2.5 pt-2">
                        <flux:modal.close>
                            <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button variant="danger" wire:click="executeClearLogs" wire:loading.attr="disabled">
                            {{ __('Truncate Logs') }}
                        </flux:button>
                    </div>
                </div>
            </flux:modal>
        </div>
    </x-pages::settings.layout>
</section>