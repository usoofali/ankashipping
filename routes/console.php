<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Production Cron Jobs (hPanel)
|--------------------------------------------------------------------------
| Add these to your Hostinger (hPanel) Cron Jobs section:
|
| 1. Laravel Scheduler (Runs scheduled tasks like whatsapp:clean-temp)
| * * * * * /opt/alt/php82/usr/bin/php /home/u832245696/domains/app.ankshipping.com/public_html/artisan schedule:run >> /dev/null 2>&1
|
| 2. Queue Worker (Processes background jobs)
| * * * * * /opt/alt/php82/usr/bin/php /home/u832245696/domains/app.ankshipping.com/public_html/artisan queue:work --stop-when-empty --tries=3 --timeout=120 >> /home/u832245696/domains/app.ankshipping.com/public_html/storage/logs/queue.log 2>&1
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Clean up WhatsApp temporary PDF files older than 1 hour, run every hour
Schedule::command('whatsapp:clean-temp --hours=1')->hourly();

// Clean up orphaned WhatsApp receipts (uploaded but not finalized) older than 2 hours, run every hour
Schedule::command('whatsapp:clean-orphaned-receipts --hours=2')->hourly();
