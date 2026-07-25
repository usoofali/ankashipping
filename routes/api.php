<?php

use App\Http\Controllers\Api\VehiclePictureController;
use App\Modules\WhatsApp\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('whatsapp')->group(function (): void {
    Route::get('webhook', [WebhookController::class, 'verify']);
    Route::post('webhook', [WebhookController::class, 'handle']);
});

Route::get('/vehicles/pictures', [VehiclePictureController::class, 'show'])->name('api.vehicles.pictures.index');
Route::get('/vehicles/{vin}/pictures', [VehiclePictureController::class, 'show'])->name('api.vehicles.pictures.show');
