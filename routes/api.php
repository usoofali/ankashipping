<?php

use App\Modules\WhatsApp\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('whatsapp')->group(function () {
    Route::get('webhook', [WebhookController::class, 'verify']);
    Route::post('webhook', [WebhookController::class, 'handle']);
});
