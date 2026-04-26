<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

final class CleanWhatsAppTempFiles extends Command
{
    protected $signature = 'whatsapp:clean-temp {--hours=1 : Delete files older than this many hours}';

    protected $description = 'Remove temporary WhatsApp PDF files generated for document delivery';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $disk = Storage::disk('public');
        $files = $disk->files('whatsapp-temp');
        $deleted = 0;

        foreach ($files as $file) {
            $lastModified = $disk->lastModified($file);

            if ($lastModified < now()->subHours($hours)->timestamp) {
                $disk->delete($file);
                $deleted++;
            }
        }

        $this->info("Cleaned {$deleted} expired WhatsApp temp file(s).");

        return self::SUCCESS;
    }
}
