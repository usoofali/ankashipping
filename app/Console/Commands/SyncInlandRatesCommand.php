<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\InlandRateService;
use Illuminate\Console\Command;

final class SyncInlandRatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inland:sync-rates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize and aggregate inland route rates and pricing trends from historical invoices';

    /**
     * Execute the console command.
     */
    public function handle(InlandRateService $service): int
    {
        $this->info('Starting inland route rates synchronization...');

        $syncedCount = $service->syncFromHistoricalInvoices();

        $this->info("Successfully synchronized {$syncedCount} inland route rates.");

        return self::SUCCESS;
    }
}
