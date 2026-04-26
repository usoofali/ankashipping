<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

final class CleanOrphanedReceipts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:clean-orphaned-receipts {--hours=2 : Delete files older than this many hours}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove orphaned WhatsApp receipts from the temporary directory';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $disk = Storage::disk('public');

        // Ensure the directory exists
        if (! $disk->exists('receipts/tmp')) {
            $this->info('Temporary receipts directory does not exist. Skipping.');

            return self::SUCCESS;
        }

        $files = $disk->files('receipts/tmp');
        $deleted = 0;

        foreach ($files as $file) {
            $lastModified = $disk->lastModified($file);

            // If file is older than X hours, it's orphaned (user cancelled or session expired)
            if ($lastModified < now()->subHours($hours)->timestamp) {
                $disk->delete($file);
                $deleted++;
            }
        }

        $this->info("Cleaned {$deleted} orphaned receipt(s) from temporary storage.");

        return self::SUCCESS;
    }
}
