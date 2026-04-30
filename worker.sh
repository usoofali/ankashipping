#!/bin/bash
flock -n /tmp/laravel_queue.lock /opt/alt/php82/usr/bin/php /home/u832245696/domains/app.ankshipping.com/public_html/artisan queue:work --tries=3 --timeout=120 --max-time=3600 --memory=128 >> /home/u832245696/domains/app.ankshipping.com/public_html/storage/logs/queue.log 2>&1


# chmod +x /home/u832245696/domains/app.ankshipping.com/public_html/worker.sh