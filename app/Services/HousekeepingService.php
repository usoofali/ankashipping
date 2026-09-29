<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HousekeepingService
{
    /**
     * Get summary counts for all housekeeping targets.
     *
     * @return array<string, array<string, int>>
     */
    public function getSummaryStats(): array
    {
        $cutoff30 = now()->subDays(30);

        return [
            'notifications' => [
                'total' => Schema::hasTable('notifications') ? DB::table('notifications')->count() : 0,
                'older_than_30d' => Schema::hasTable('notifications') ? DB::table('notifications')->where('created_at', '<=', $cutoff30)->count() : 0,
            ],
            'email_logs' => [
                'total' => Schema::hasTable('email_logs') ? DB::table('email_logs')->count() : 0,
                'older_than_30d' => Schema::hasTable('email_logs') ? DB::table('email_logs')->where('created_at', '<=', $cutoff30)->count() : 0,
            ],
            'failed_jobs' => [
                'total' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
                'older_than_30d' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->where('failed_at', '<=', $cutoff30)->count() : 0,
            ],
            'activity_logs' => [
                'total' => Schema::hasTable('activity_logs') ? DB::table('activity_logs')->count() : 0,
                'older_than_30d' => Schema::hasTable('activity_logs') ? DB::table('activity_logs')->where('created_at', '<=', $cutoff30)->count() : 0,
            ],
        ];
    }

    /**
     * Prune database notifications in safe batches.
     */
    public function pruneNotifications(?int $days = null, bool $onlyRead = false): int
    {
        if (! Schema::hasTable('notifications')) {
            return 0;
        }

        $cutoff = $days !== null ? now()->subDays($days) : null;
        $totalDeleted = 0;

        do {
            $query = DB::table('notifications');

            if ($cutoff !== null) {
                $query->where('created_at', '<=', $cutoff);
            }

            if ($onlyRead) {
                $query->whereNotNull('read_at');
            }

            $ids = $query->limit(500)->pluck('id');

            if ($ids->isNotEmpty()) {
                $deleted = DB::table('notifications')->whereIn('id', $ids)->delete();
                $totalDeleted += $deleted;
            }
        } while ($ids->isNotEmpty());

        return $totalDeleted;
    }

    /**
     * Prune email logs and their associated attempts in safe batches.
     */
    public function pruneEmailLogs(?int $days = null): int
    {
        if (! Schema::hasTable('email_logs')) {
            return 0;
        }

        $cutoff = $days !== null ? now()->subDays($days) : null;
        $totalDeleted = 0;

        do {
            $query = DB::table('email_logs');

            if ($cutoff !== null) {
                $query->where('created_at', '<=', $cutoff);
            }

            $ids = $query->limit(500)->pluck('id');

            if ($ids->isNotEmpty()) {
                if (Schema::hasTable('email_attempts')) {
                    DB::table('email_attempts')->whereIn('email_log_id', $ids)->delete();
                }

                $deleted = DB::table('email_logs')->whereIn('id', $ids)->delete();
                $totalDeleted += $deleted;
            }
        } while ($ids->isNotEmpty());

        return $totalDeleted;
    }

    /**
     * Flush failed queue jobs in safe batches or all at once.
     */
    public function flushFailedJobs(?int $days = null): int
    {
        if (! Schema::hasTable('failed_jobs')) {
            return 0;
        }

        if ($days === null) {
            $count = DB::table('failed_jobs')->count();
            Artisan::call('queue:flush');

            return $count;
        }

        $cutoff = now()->subDays($days);
        $totalDeleted = 0;

        do {
            $ids = DB::table('failed_jobs')
                ->where('failed_at', '<=', $cutoff)
                ->limit(500)
                ->pluck('id');

            if ($ids->isNotEmpty()) {
                $deleted = DB::table('failed_jobs')->whereIn('id', $ids)->delete();
                $totalDeleted += $deleted;
            }
        } while ($ids->isNotEmpty());

        return $totalDeleted;
    }

    /**
     * Prune activity audit logs in safe batches.
     */
    public function pruneActivityLogs(?int $days = null): int
    {
        if (! Schema::hasTable('activity_logs')) {
            return 0;
        }

        $cutoff = $days !== null ? now()->subDays($days) : null;
        $totalDeleted = 0;

        do {
            $query = DB::table('activity_logs');

            if ($cutoff !== null) {
                $query->where('created_at', '<=', $cutoff);
            }

            $ids = $query->limit(500)->pluck('id');

            if ($ids->isNotEmpty()) {
                $deleted = DB::table('activity_logs')->whereIn('id', $ids)->delete();
                $totalDeleted += $deleted;
            }
        } while ($ids->isNotEmpty());

        return $totalDeleted;
    }

    /**
     * Bulk prune all old ephemeral data older than given days.
     *
     * @return array<string, int>
     */
    public function pruneAllOldData(int $days = 30): array
    {
        return [
            'notifications' => $this->pruneNotifications($days),
            'email_logs' => $this->pruneEmailLogs($days),
            'failed_jobs' => $this->flushFailedJobs($days),
            'activity_logs' => $this->pruneActivityLogs($days),
        ];
    }
}
