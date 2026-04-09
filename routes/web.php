<?php

use App\Http\Controllers\Auth\RegisterGeoOptionsController;
use App\Http\Controllers\DriverOptionsController;
use App\Http\Controllers\ImportTemplateController;
use App\Http\Controllers\ShipmentDocumentFileDownloadController;
use App\Http\Controllers\ShipmentDocumentFileSignedDownloadController;
use App\Http\Controllers\ShipmentInvoiceController;
use App\Http\Controllers\ShipperOptionsController;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    try {
        if (! User::role('super_admin')->exists() && ! File::exists(storage_path('app/setup-complete'))) {
            return redirect()->route('setup');
        }
    } catch (Throwable) {
        return redirect()->route('setup');
    }

    return redirect()->route('login');
})->name('home');

Route::livewire('/setup', 'pages::auth.setup')
    ->middleware('setup.access')
    ->name('setup');

Route::view('/terms', 'pages.legal.terms')->name('terms');
Route::view('/privacy', 'pages.legal.privacy')->name('privacy');

Route::middleware(['web', 'signed', 'throttle:60,1'])->group(function (): void {
    Route::get('/shipments/{shipment}/documents/files/{file}/signed', ShipmentDocumentFileSignedDownloadController::class)
        ->whereNumber('shipment')
        ->whereNumber('file')
        ->name('shipments.documents.files.download.signed');
});

Route::middleware(['web', 'throttle:120,1'])->group(function (): void {
    Route::get('/register/geo/countries', [RegisterGeoOptionsController::class, 'countries'])->name('register.geo.countries');
    Route::get('/register/geo/states', [RegisterGeoOptionsController::class, 'states'])->name('register.geo.states');
    Route::get('/register/geo/cities', [RegisterGeoOptionsController::class, 'cities'])->name('register.geo.cities');
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('/notifications', 'pages::notifications.index')->name('notifications.index');

    Route::livewire('/newsletters', 'pages::newsletters.index')->name('newsletters.index');
    Route::livewire('/email-logs', 'pages::email-logs.index')->name('email-logs.index');
    Route::livewire('/failed-jobs', 'pages::failed-jobs.index')->name('failed-jobs.index');

    Route::livewire('/shippers', 'pages::shippers.index')->name('shippers.index');
    Route::livewire('/shippers/{shipper}', 'pages::shippers.show')
        ->whereNumber('shipper')
        ->name('shippers.show');

    // Prealerts
    Route::livewire('/prealerts', 'pages::prealerts.index')->name('prealerts.index');
    Route::livewire('/prealerts/create', 'pages::prealerts.create')->name('prealerts.create');
    Route::livewire('/prealerts/{prealert}/edit', 'pages::prealerts.edit')
        ->whereNumber('prealert')
        ->name('prealerts.edit');

    // Shipments
    Route::livewire('/shipments', 'pages::shipments.index')->name('shipments.index');
    Route::livewire('/shipments/create', 'pages::shipments.create')->name('shipments.create');
    Route::livewire('/shipments/{shipment}/edit', 'pages::shipments.edit')
        ->whereNumber('shipment')
        ->name('shipments.edit');
    Route::livewire('/shipments/{shipment}', 'pages::shipments.show')
        ->whereNumber('shipment')
        ->name('shipments.show');

    Route::get('/shipments/{shipment}/invoice', [ShipmentInvoiceController::class, 'download'])
        ->whereNumber('shipment')
        ->name('shipments.invoice.download');

    Route::get('/shipments/{shipment}/documents/files/{file}', ShipmentDocumentFileDownloadController::class)
        ->whereNumber('shipment')
        ->whereNumber('file')
        ->name('shipments.documents.files.download');

    Route::get('/api/shippers/search', [ShipperOptionsController::class, 'index'])->name('api.shippers.search');

    Route::get('/api/drivers/search', [DriverOptionsController::class, 'index'])->name('api.drivers.search');
    Route::get('/import-templates/geo/{entity}', [ImportTemplateController::class, 'geo'])->name('import-templates.geo');

    // Master Data
    Route::livewire('/default-shipment-settings', 'pages::default-shipment-settings.index')
        ->middleware('permission:default_shipment_settings.view')
        ->name('default-shipment-settings.index');
    Route::livewire('/payment-methods', 'pages::payment-methods.index')->name('payment_methods.index');
    Route::livewire('/charge-items', 'pages::charge-items.index')->name('charge-items.index');
    Route::livewire('/carriers', 'pages::carriers.index')->name('carriers.index');
    Route::livewire('/ports', 'pages::ports.index')->name('ports.index');
    Route::livewire('/countries', 'pages::countries.index')->name('countries.index');
    Route::livewire('/states', 'pages::states.index')->name('states.index');
    Route::livewire('/cities', 'pages::cities.index')->name('cities.index');

    // Operations Master Data
    Route::livewire('/drivers', 'pages::drivers.index')->name('drivers.index');
    Route::livewire('/staff', 'pages::staff.index')->name('staff.index');
    Route::livewire('/workshops', 'pages::workshops.index')->name('workshops.index');

    // Shipper Wallet
    Route::livewire('/shipper/wallet', 'pages::shipper.wallet.index')
        ->middleware('permission:wallets.view')
        ->name('shipper.wallet.index');

    // Financials
    Route::livewire('/financials/top-ups', 'pages::financials.top-ups.index')
        ->middleware('permission:wallet_top_ups.view')
        ->name('financials.top-ups.index');

    Route::livewire('/financials/wallets', 'pages::financials.wallets.index')
        ->middleware('permission:wallets.view')
        ->name('financials.wallets.index');

    Route::livewire('/financials/wallets/{wallet}', 'pages::financials.wallets.show')
        ->middleware('permission:wallets.view')
        ->name('financials.wallets.show');
});

require __DIR__.'/settings.php';
