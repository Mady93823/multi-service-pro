<?php

use App\Http\Controllers\Installer\InstallerController;
use Illuminate\Support\Facades\Route;

Route::prefix('install')->name('install.')->group(function () {
    Route::get('/', [InstallerController::class, 'requirements'])->name('requirements');
    Route::get('database', [InstallerController::class, 'database'])->name('database');
    Route::post('database', [InstallerController::class, 'storeDatabase'])->name('database.store');
    Route::get('migrate', [InstallerController::class, 'migrate'])->name('migrate');
    Route::post('migrate', [InstallerController::class, 'runMigrate'])->name('migrate.run');
    Route::get('admin', [InstallerController::class, 'admin'])->name('admin');
    Route::post('admin', [InstallerController::class, 'storeAdmin'])->name('admin.store');
    Route::get('finish', [InstallerController::class, 'finish'])->name('finish');
});
