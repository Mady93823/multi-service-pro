<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Customer;
use App\Http\Controllers\DemoPingController;
use App\Http\Controllers\Provider;
use Illuminate\Support\Facades\Route;

Route::get('/', [Customer\CatalogController::class, 'index'])->name('home');

Route::get('services', [Customer\CatalogController::class, 'index'])->name('catalog.index');
Route::get('services/{category:slug}', [Customer\CatalogController::class, 'category'])->name('catalog.category');
Route::get('services/{category:slug}/{service:slug}', [Customer\CatalogController::class, 'show'])
    ->name('catalog.show')
    ->scopeBindings();

Route::middleware(['auth', 'role:customer'])->group(function () {
    Route::get('dashboard', Customer\DashboardController::class)->name('dashboard');
});

Route::middleware(['auth', 'role:provider'])->prefix('provider')->name('provider.')->group(function () {
    Route::get('dashboard', Provider\DashboardController::class)->name('dashboard');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', Admin\DashboardController::class)->name('dashboard');
    Route::resource('categories', Admin\CategoryController::class)->except(['show']);
    Route::resource('services', Admin\ServiceController::class)->except(['show']);
    Route::get('settings', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
});

// Phase 1 WebSocket smoke test; removed when Phase 3 realtime features land.
Route::post('demo/ping', DemoPingController::class)
    ->middleware(['auth', 'throttle:10,1'])
    ->name('demo.ping');

require __DIR__.'/installer.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
