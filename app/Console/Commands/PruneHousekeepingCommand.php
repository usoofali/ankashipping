<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\HousekeepingService;
use Illuminate\Console\Command;

final class PruneHousekeepingCommand extends Command
{
    protected $signature = 'housekeeping:prune {--days=30 : Delete records older than this many days}';

    protected $description = 'Prune old ephemeral database records (notifications, email logs, failed jobs, activity logs)';

    public function handle(HousekeepingService $service): int
    {
        $days = (int) $this->option('days');
        $this->info("Pruning ephemeral records older than {$days} days...");

        $results = $service->pruneAllOldData($days);

        $this->table(
            ['Target', 'Deleted Records'],
            [
                ['Notifications', $results['notifications']],
                ['Email Logs', $results['email_logs']],
                ['Failed Jobs', $results['failed_jobs']],
                ['Activity Logs', $results['activity_logs']],
            ]
        );

        $total = array_sum($results);
        $this->info("Housekeeping completed successfully: {$total} total record(s) pruned.");

        return self::SUCCESS;
    }
}
