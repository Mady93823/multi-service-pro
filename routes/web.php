<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\BookingPhotoController;
use App\Http\Controllers\Customer;
use App\Http\Controllers\DemoPingController;
use App\Http\Controllers\GeocodeController;
use App\Http\Controllers\Provider;
use App\Http\Controllers\ProviderDocumentController;
use Illuminate\Support\Facades\Route;

Route::get('/', [Customer\CatalogController::class, 'index'])->name('home');

Route::get('services', [Customer\CatalogController::class, 'index'])->name('catalog.index');
Route::get('services/{category:slug}', [Customer\CatalogController::class, 'category'])->name('catalog.category');
Route::get('services/{category:slug}/{service:slug}', [Customer\CatalogController::class, 'show'])
    ->name('catalog.show')
    ->scopeBindings();

// Session cart — guests can build one; login happens at checkout.
Route::get('cart', [Customer\CartController::class, 'show'])->name('cart.show');
Route::post('cart/items', [Customer\CartController::class, 'add'])->name('cart.add');
Route::patch('cart/items/{key}', [Customer\CartController::class, 'update'])->name('cart.update');
Route::delete('cart/items/{key}', [Customer\CartController::class, 'remove'])->name('cart.remove');

Route::middleware(['auth', 'role:customer'])->group(function () {
    Route::get('dashboard', Customer\DashboardController::class)->name('dashboard');
    Route::resource('addresses', Customer\AddressController::class)->except(['show']);
    Route::put('addresses/{address}/default', [Customer\AddressController::class, 'setDefault'])
        ->name('addresses.default');

    Route::get('checkout', [Customer\CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('checkout', [Customer\CheckoutController::class, 'store'])->name('checkout.store');

    Route::get('bookings', [Customer\BookingController::class, 'index'])->name('bookings.index');
    Route::get('bookings/{booking}', [Customer\BookingController::class, 'show'])->name('bookings.show');
    Route::post('bookings/{booking}/cancel', [Customer\BookingController::class, 'cancel'])->name('bookings.cancel');
    Route::post('bookings/{booking}/reschedule', [Customer\BookingController::class, 'reschedule'])->name('bookings.reschedule');
    Route::post('bookings/{booking}/rebook', [Customer\BookingController::class, 'rebook'])->name('bookings.rebook');
    Route::post('providers/{provider}/favorite', [Customer\FavoriteProviderController::class, 'toggle'])->name('providers.favorite');
});

// Private-disk booking photos — BookingPolicy decides (customer, assigned provider, admin).
Route::get('bookings/{booking}/photos/{media}', [BookingPhotoController::class, 'show'])
    ->middleware('auth')
    ->name('bookings.photos.show');

// Map-picker helpers (Nominatim proxy) — used by customer address book and admin zone editor.
Route::middleware(['auth', 'throttle:30,1'])->group(function () {
    Route::get('geocode/reverse', [GeocodeController::class, 'reverse'])->name('geocode.reverse');
    Route::get('geocode/search', [GeocodeController::class, 'search'])->name('geocode.search');
});

Route::middleware(['auth', 'role:provider'])->prefix('provider')->name('provider.')->group(function () {
    // Onboarding is reachable in every approval state; the dashboard
    // (and later panel screens) sit behind provider.approved.
    Route::get('onboarding', [Provider\OnboardingController::class, 'show'])->name('onboarding');
    Route::put('profile', [Provider\ProfileController::class, 'update'])->name('profile.update');
    Route::post('documents', [Provider\DocumentController::class, 'store'])->name('documents.store');

    Route::middleware('provider.approved')->group(function () {
        Route::get('dashboard', Provider\DashboardController::class)->name('dashboard');
        Route::post('availability/online', [Provider\AvailabilityController::class, 'toggleOnline'])->name('availability.online');
        Route::post('blackouts', [Provider\AvailabilityController::class, 'storeBlackout'])->name('blackouts.store');
        Route::delete('blackouts/{blackout}', [Provider\AvailabilityController::class, 'destroyBlackout'])->name('blackouts.destroy');
    });
});

// Private-disk KYC files — owning provider or admin only.
Route::get('provider-documents/{document}', [ProviderDocumentController::class, 'show'])
    ->middleware('auth')
    ->name('provider-documents.show');

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', Admin\DashboardController::class)->name('dashboard');
    Route::resource('categories', Admin\CategoryController::class)->except(['show']);
    Route::resource('services', Admin\ServiceController::class)->except(['show']);
    Route::resource('zones', Admin\ZoneController::class)->except(['show']);
    Route::get('bookings', [Admin\BookingController::class, 'index'])->name('bookings.index');
    Route::get('bookings/{booking}', [Admin\BookingController::class, 'show'])->name('bookings.show');
    Route::post('bookings/{booking}/transition', [Admin\BookingController::class, 'transition'])->name('bookings.transition');
    Route::get('providers', [Admin\ProviderController::class, 'index'])->name('providers.index');
    Route::get('providers/{provider}', [Admin\ProviderController::class, 'show'])->name('providers.show');
    Route::post('providers/{provider}/review', [Admin\ProviderController::class, 'review'])->name('providers.review');
    Route::post('provider-documents/{document}/review', [Admin\ProviderController::class, 'reviewDocument'])->name('provider-documents.review');
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
