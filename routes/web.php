<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\BookingPhotoController;
use App\Http\Controllers\Customer;
use App\Http\Controllers\DemoPingController;
use App\Http\Controllers\FcmTokenController;
use App\Http\Controllers\GeocodeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Provider;
use App\Http\Controllers\ProviderDocumentController;
use App\Http\Controllers\ReviewPhotoController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\WebhookController;
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
    Route::get('wallet', [Customer\WalletController::class, 'show'])->name('wallet.show');

    // Online payment leg (M08): pay page + gateway session + confirmations.
    Route::get('bookings/{booking}/pay', [Customer\PaymentController::class, 'show'])->name('bookings.pay');
    Route::post('bookings/{booking}/pay/wallet', [Customer\PaymentController::class, 'payWithWallet'])->name('payments.wallet');
    Route::post('bookings/{booking}/pay/razorpay/callback', [Customer\PaymentController::class, 'razorpayCallback'])->name('payments.razorpay.callback');
    Route::get('bookings/{booking}/pay/stripe/return', [Customer\PaymentController::class, 'stripeReturn'])->name('payments.stripe.return');
    Route::post('bookings/{booking}/pay/{provider}', [Customer\PaymentController::class, 'initiate'])
        ->whereIn('provider', ['razorpay', 'stripe'])->name('payments.initiate');

    Route::get('bookings/{booking}', [Customer\BookingController::class, 'show'])->name('bookings.show');
    Route::post('bookings/{booking}/cancel', [Customer\BookingController::class, 'cancel'])->name('bookings.cancel');
    Route::post('bookings/{booking}/reschedule', [Customer\BookingController::class, 'reschedule'])->name('bookings.reschedule');
    Route::post('bookings/{booking}/rebook', [Customer\BookingController::class, 'rebook'])->name('bookings.rebook');
    Route::post('bookings/{booking}/review', [Customer\ReviewController::class, 'store'])->name('bookings.review.store');
    Route::post('providers/{provider}/favorite', [Customer\FavoriteProviderController::class, 'toggle'])->name('providers.favorite');
});

// Private-disk booking photos — BookingPolicy decides (customer, assigned provider, admin).
Route::get('bookings/{booking}/photos/{media}', [BookingPhotoController::class, 'show'])
    ->middleware('auth')
    ->name('bookings.photos.show');

// GST tax invoice PDF (M09) — BookingPolicy@invoice: the customer or an admin.
Route::get('bookings/{booking}/invoice', InvoiceController::class)
    ->middleware('auth')
    ->name('bookings.invoice');

// Review photos (M10) — private disk but guest-reachable: the storefront
// shows them. ReviewPolicy@view decides; hidden reviews 404.
Route::get('reviews/{review}/photos/{media}', [ReviewPhotoController::class, 'show'])
    ->name('reviews.photos.show');

// Map-picker helpers (Nominatim proxy) — used by customer address book and admin zone editor.
Route::middleware(['auth', 'throttle:30,1'])->group(function () {
    Route::get('geocode/reverse', [GeocodeController::class, 'reverse'])->name('geocode.reverse');
    Route::get('geocode/search', [GeocodeController::class, 'search'])->name('geocode.search');
});

// Cross-role authenticated endpoints (M07 tracking fallback, M11 notifications).
Route::middleware('auth')->group(function () {
    Route::get('bookings/{booking}/tracking/last', [TrackingController::class, 'last'])->name('tracking.last');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::post('fcm-tokens', [FcmTokenController::class, 'store'])->name('fcm-tokens.store');
    Route::delete('fcm-tokens', [FcmTokenController::class, 'destroy'])->name('fcm-tokens.destroy');
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

        // Earnings + payouts (M09).
        Route::get('earnings', [Provider\EarningController::class, 'index'])->name('earnings.index');
        Route::post('payouts', [Provider\EarningController::class, 'requestPayout'])->name('payouts.store');

        // Dispatch (M06): job offers + the provider's own job progression.
        Route::get('jobs', [Provider\JobController::class, 'index'])->name('jobs.index');
        Route::post('jobs/{booking}/advance', [Provider\JobController::class, 'advance'])->name('jobs.advance');
        Route::post('offers/{offer}/accept', [Provider\JobController::class, 'acceptOffer'])->name('offers.accept');
        Route::post('offers/{offer}/decline', [Provider\JobController::class, 'declineOffer'])->name('offers.decline');

        // Live tracking (M07): journey screen + the JSON GPS loop.
        Route::get('jobs/{booking}/journey', [Provider\TrackingController::class, 'journey'])->name('jobs.journey');
        Route::post('jobs/{booking}/tracking/start', [Provider\TrackingController::class, 'start'])->name('tracking.start');
        Route::post('jobs/{booking}/tracking/ping', [Provider\TrackingController::class, 'ping'])
            ->middleware('throttle:60,1')->name('tracking.ping');
        Route::post('jobs/{booking}/tracking/stop', [Provider\TrackingController::class, 'stop'])->name('tracking.stop');
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
    Route::post('bookings/{booking}/dispatch', [Admin\BookingController::class, 'dispatch'])->name('bookings.dispatch');
    Route::post('bookings/{booking}/refund', [Admin\BookingController::class, 'refund'])->name('bookings.refund');
    // Payout queue (M09).
    Route::get('payouts', [Admin\PayoutController::class, 'index'])->name('payouts.index');
    Route::post('payouts/{payout}/approve', [Admin\PayoutController::class, 'approve'])->name('payouts.approve');
    Route::post('payouts/{payout}/pay', [Admin\PayoutController::class, 'pay'])->name('payouts.pay');
    Route::post('payouts/{payout}/reject', [Admin\PayoutController::class, 'reject'])->name('payouts.reject');
    // Review moderation (M10).
    Route::get('reviews', [Admin\ReviewController::class, 'index'])->name('reviews.index');
    Route::post('reviews/{review}/hide', [Admin\ReviewController::class, 'hide'])->name('reviews.hide');
    Route::post('reviews/{review}/unhide', [Admin\ReviewController::class, 'unhide'])->name('reviews.unhide');
    Route::get('providers', [Admin\ProviderController::class, 'index'])->name('providers.index');
    Route::get('providers/{provider}', [Admin\ProviderController::class, 'show'])->name('providers.show');
    Route::post('providers/{provider}/review', [Admin\ProviderController::class, 'review'])->name('providers.review');
    Route::post('provider-documents/{document}/review', [Admin\ProviderController::class, 'reviewDocument'])->name('provider-documents.review');
    Route::get('settings', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
});

// Gateway webhooks (M08): unauthenticated, signature-verified, CSRF-exempt
// (bootstrap/app.php), throttled against floods.
Route::post('webhooks/razorpay', [WebhookController::class, 'razorpay'])
    ->middleware('throttle:60,1')->name('webhooks.razorpay');
Route::post('webhooks/stripe', [WebhookController::class, 'stripe'])
    ->middleware('throttle:60,1')->name('webhooks.stripe');

// Phase 1 WebSocket smoke test; removed when Phase 3 realtime features land.
Route::post('demo/ping', DemoPingController::class)
    ->middleware(['auth', 'throttle:10,1'])
    ->name('demo.ping');

require __DIR__.'/installer.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
