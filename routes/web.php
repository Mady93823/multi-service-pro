<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\BecomeProviderController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BookingPhotoController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Customer;
use App\Http\Controllers\DemoPingController;
use App\Http\Controllers\FaviconController;
use App\Http\Controllers\FcmTokenController;
use App\Http\Controllers\GeocodeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentProofController;
use App\Http\Controllers\Provider;
use App\Http\Controllers\ProviderDocumentController;
use App\Http\Controllers\ReviewPhotoController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\TicketAttachmentController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// The home page is a CMS page built from blocks (M20) — nothing on it is
// hardcoded; the search box and the grids are blocks an admin can move.
Route::get('/', [Customer\HomeController::class, 'index'])->name('home');

// CMS pages (M14) — reserved /p/ prefix so a page slug can never shadow a route.
Route::get('p/{page:slug}', [Customer\PageController::class, 'show'])->name('pages.show');

// Generated, for the same reason robots.txt is: a static icon would be *our*
// mark on the buyer's tab. Overridden the moment they upload one (M14/D8).
Route::get('favicon.svg', FaviconController::class)->name('favicon');

// M24: both are generated, not static files (public/robots.txt was deleted) —
// a white-label install's URLs depend on its own content.
Route::get('sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('robots.txt', [SeoController::class, 'robots'])->name('seo.robots');

// Blog (M21). `feed` is declared before `{post:slug}` — otherwise a post could
// never be called "feed", and the slug route would swallow the RSS URL.
Route::get('blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('blog/feed', [BlogController::class, 'feed'])->name('blog.feed');
Route::get('blog/{post:slug}', [BlogController::class, 'show'])->name('blog.show');

// Provider recruitment pitch (M19). The CTA lands on /register?as=provider —
// the account is created by the ordinary register flow, not a second one.
Route::get('become-a-provider', [BecomeProviderController::class, 'show'])->name('provider.join');

// Contact form (M19) — a submission opens a support ticket (M16), guests
// included. Throttled + honeypot: the form is public and bots find it.
Route::get('contact', [ContactController::class, 'show'])->name('contact.show');
Route::post('contact', [ContactController::class, 'store'])
    ->middleware('throttle:5,1')->name('contact.store');

// Newsletter signup from the footer.
Route::post('newsletter', [NewsletterController::class, 'store'])
    ->middleware('throttle:5,1')->name('newsletter.store');

// City switcher (M25) — a browsing preference in the session, open to guests.
Route::post('city/{city}', [CityController::class, 'switch'])
    ->middleware('throttle:public-write')->name('city.switch');

// "Use my location" (M25/M03): a GPS fix resolves to a zone (or the nearest
// one) and its city, both stored in the session. Guests included.
Route::post('location/detect', [LocationController::class, 'detect'])
    ->middleware('throttle:public-write')->name('location.detect');

Route::get('services', [Customer\CatalogController::class, 'index'])->name('catalog.index');
Route::get('services/{category:slug}', [Customer\CatalogController::class, 'category'])->name('catalog.category');
Route::get('services/{category:slug}/{service:slug}', [Customer\CatalogController::class, 'show'])
    ->name('catalog.show')
    ->scopeBindings();

// Session cart — guests can build one; login happens at checkout. The writes are
// unauthenticated, so they carry the guest limiter: nothing else is under them.
Route::get('cart', [Customer\CartController::class, 'show'])->name('cart.show');
Route::middleware('throttle:public-write')->group(function () {
    Route::post('cart/items', [Customer\CartController::class, 'add'])->name('cart.add');
    Route::patch('cart/items/{key}', [Customer\CartController::class, 'update'])->name('cart.update');
    Route::delete('cart/items/{key}', [Customer\CartController::class, 'remove'])->name('cart.remove');
});

Route::middleware(['auth', 'role:customer'])->group(function () {
    Route::get('dashboard', Customer\DashboardController::class)->name('dashboard');
    Route::resource('addresses', Customer\AddressController::class)->except(['show']);
    Route::put('addresses/{address}/default', [Customer\AddressController::class, 'setDefault'])
        ->name('addresses.default');

    Route::get('checkout', [Customer\CheckoutController::class, 'show'])->name('checkout.show');
    // Placing a booking can carry problem photos, so it counts against the
    // upload budget rather than getting a limit of its own.
    Route::post('checkout', [Customer\CheckoutController::class, 'store'])
        ->middleware('throttle:uploads')->name('checkout.store');

    // Coupons (M12): session-scoped apply/remove; throttled — codes are guessable.
    Route::post('checkout/coupon', [Customer\CouponController::class, 'store'])
        ->middleware('throttle:10,1')->name('checkout.coupon.store');
    Route::delete('checkout/coupon', [Customer\CouponController::class, 'destroy'])->name('checkout.coupon.destroy');

    Route::get('bookings', [Customer\BookingController::class, 'index'])->name('bookings.index');
    Route::get('wallet', [Customer\WalletController::class, 'show'])->name('wallet.show');

    // Online payment leg (M08): pay page + gateway session + confirmations.
    Route::get('bookings/{booking}/pay', [Customer\PaymentController::class, 'show'])->name('bookings.pay');
    Route::post('bookings/{booking}/pay/wallet', [Customer\PaymentController::class, 'payWithWallet'])->name('payments.wallet');
    // Offline / bank transfer (M22): declares a transfer + uploads the proof.
    // Settles nothing — an admin verifies it through ConfirmPayment (D27).
    Route::post('bookings/{booking}/pay/offline', [Customer\PaymentController::class, 'offline'])
        ->middleware('throttle:uploads')->name('payments.offline');
    Route::post('bookings/{booking}/pay/razorpay/callback', [Customer\PaymentController::class, 'razorpayCallback'])->name('payments.razorpay.callback');
    Route::get('bookings/{booking}/pay/stripe/return', [Customer\PaymentController::class, 'stripeReturn'])->name('payments.stripe.return');
    Route::post('bookings/{booking}/pay/{provider}', [Customer\PaymentController::class, 'initiate'])
        ->whereIn('provider', ['razorpay', 'stripe'])->name('payments.initiate');

    Route::get('bookings/{booking}', [Customer\BookingController::class, 'show'])->name('bookings.show');
    Route::post('bookings/{booking}/cancel', [Customer\BookingController::class, 'cancel'])->name('bookings.cancel');
    Route::post('bookings/{booking}/reschedule', [Customer\BookingController::class, 'reschedule'])->name('bookings.reschedule');
    Route::post('bookings/{booking}/rebook', [Customer\BookingController::class, 'rebook'])->name('bookings.rebook');
    Route::post('bookings/{booking}/review', [Customer\ReviewController::class, 'store'])
        ->middleware('throttle:uploads')->name('bookings.review.store');
    Route::post('providers/{provider}/favorite', [Customer\FavoriteProviderController::class, 'toggle'])->name('providers.favorite');
});

// Private-disk booking photos — BookingPolicy decides (customer, assigned provider, admin).
Route::get('bookings/{booking}/photos/{media}', [BookingPhotoController::class, 'show'])
    ->middleware('auth')
    ->name('bookings.photos.show');

// Offline-payment proof (M22) — private disk; PaymentPolicy@view: the customer
// who uploaded it or an admin. The assigned provider is not a party to it.
Route::get('payments/{payment}/proof/{media}', [PaymentProofController::class, 'show'])
    ->middleware('auth')
    ->name('payments.proof.show');

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

// Help centre (M16) — customers and providers share one controller; the
// page picks its shell from the role. Providers can reach it before
// approval (KYC trouble is exactly what support is for).
Route::middleware(['auth', 'role:customer|provider'])->prefix('support')->name('support.')->group(function () {
    Route::get('tickets', [SupportTicketController::class, 'index'])->name('tickets.index');
    Route::get('tickets/create', [SupportTicketController::class, 'create'])->name('tickets.create');
    Route::post('tickets', [SupportTicketController::class, 'store'])
        ->middleware('throttle:uploads')->name('tickets.store');
    Route::get('tickets/{ticket}', [SupportTicketController::class, 'show'])->name('tickets.show');
    Route::post('tickets/{ticket}/reply', [SupportTicketController::class, 'reply'])
        ->middleware('throttle:uploads')->name('tickets.reply');
});

// Private-disk ticket attachments — SupportTicketPolicy decides (owner or
// admin), so the route sits outside the role groups.
Route::get('support/tickets/{ticket}/attachments/{media}', [TicketAttachmentController::class, 'show'])
    ->middleware('auth')
    ->name('support.attachments.show');

// Cross-role authenticated endpoints (M07 tracking fallback, M11 notifications).
Route::middleware('auth')->group(function () {
    // The customer map polls this whenever Echo drops (M07). One call per few
    // seconds is the design; a tab left open for an hour must not be a load test.
    Route::get('bookings/{booking}/tracking/last', [TrackingController::class, 'last'])
        ->middleware('throttle:60,1')->name('tracking.last');

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
    Route::post('documents', [Provider\DocumentController::class, 'store'])
        ->middleware('throttle:uploads')->name('documents.store');

    Route::middleware('provider.approved')->group(function () {
        Route::get('dashboard', Provider\DashboardController::class)->name('dashboard');
        Route::post('availability/online', [Provider\AvailabilityController::class, 'toggleOnline'])->name('availability.online');
        Route::post('blackouts', [Provider\AvailabilityController::class, 'storeBlackout'])->name('blackouts.store');
        Route::delete('blackouts/{blackout}', [Provider\AvailabilityController::class, 'destroyBlackout'])->name('blackouts.destroy');

        // Earnings + payouts (M09); payout destinations are saved rows (M22).
        Route::get('earnings', [Provider\EarningController::class, 'index'])->name('earnings.index');
        Route::post('payouts', [Provider\EarningController::class, 'requestPayout'])->name('payouts.store');
        Route::post('payout-accounts', [Provider\PayoutAccountController::class, 'store'])->name('payout-accounts.store');
        Route::put('payout-accounts/{account}', [Provider\PayoutAccountController::class, 'update'])->name('payout-accounts.update');
        Route::delete('payout-accounts/{account}', [Provider\PayoutAccountController::class, 'destroy'])->name('payout-accounts.destroy');

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
    // Locations (M25): zones belong to a city row, not to a typed-in name.
    Route::resource('cities', Admin\CityController::class)->except(['show']);
    Route::resource('zones', Admin\ZoneController::class)->except(['show']);
    Route::get('bookings', [Admin\BookingController::class, 'index'])->name('bookings.index');
    Route::get('bookings/{booking}', [Admin\BookingController::class, 'show'])->name('bookings.show');
    Route::post('bookings/{booking}/transition', [Admin\BookingController::class, 'transition'])->name('bookings.transition');
    Route::post('bookings/{booking}/dispatch', [Admin\BookingController::class, 'dispatch'])->name('bookings.dispatch');
    Route::post('bookings/{booking}/refund', [Admin\BookingController::class, 'refund'])->name('bookings.refund');
    // Payments hub (M22): every payment row in one place. Offline rows are
    // verified/rejected here, through the same ConfirmPayment a webhook calls.
    Route::get('payments', [Admin\PaymentController::class, 'index'])->name('payments.index');
    Route::post('payments/{payment}/verify', [Admin\PaymentController::class, 'verify'])->name('payments.verify');
    Route::post('payments/{payment}/reject', [Admin\PaymentController::class, 'reject'])->name('payments.reject');
    // The accounts customers transfer into (M22).
    Route::get('bank-accounts', [Admin\BankAccountController::class, 'index'])->name('bank-accounts.index');
    Route::post('bank-accounts', [Admin\BankAccountController::class, 'store'])->name('bank-accounts.store');
    Route::put('bank-accounts/{account}', [Admin\BankAccountController::class, 'update'])->name('bank-accounts.update');
    Route::delete('bank-accounts/{account}', [Admin\BankAccountController::class, 'destroy'])->name('bank-accounts.destroy');
    // Payout queue (M09); the destination is a stored account (M22).
    Route::get('payouts', [Admin\PayoutController::class, 'index'])->name('payouts.index');
    Route::post('payouts/{payout}/approve', [Admin\PayoutController::class, 'approve'])->name('payouts.approve');
    Route::post('payouts/{payout}/pay', [Admin\PayoutController::class, 'pay'])->name('payouts.pay');
    Route::post('payouts/{payout}/reject', [Admin\PayoutController::class, 'reject'])->name('payouts.reject');
    Route::post('payout-accounts/{account}/verify', [Admin\PayoutController::class, 'verifyAccount'])->name('payout-accounts.verify');
    // Review moderation (M10).
    Route::get('reviews', [Admin\ReviewController::class, 'index'])->name('reviews.index');
    Route::post('reviews/{review}/hide', [Admin\ReviewController::class, 'hide'])->name('reviews.hide');
    Route::post('reviews/{review}/unhide', [Admin\ReviewController::class, 'unhide'])->name('reviews.unhide');
    Route::post('reviews/{review}/promote', [Admin\ReviewController::class, 'promote'])->name('reviews.promote');
    Route::get('providers', [Admin\ProviderController::class, 'index'])->name('providers.index');
    Route::get('providers/{provider}', [Admin\ProviderController::class, 'show'])->name('providers.show');
    Route::post('providers/{provider}/review', [Admin\ProviderController::class, 'review'])->name('providers.review');
    Route::post('provider-documents/{document}/review', [Admin\ProviderController::class, 'reviewDocument'])->name('provider-documents.review');
    // Coupons + banners (M12).
    Route::resource('coupons', Admin\CouponController::class)->except(['show']);
    Route::resource('banners', Admin\BannerController::class)->except(['show']);
    // Marketing content (M19): testimonials, sponsors, popups, newsletter list.
    Route::get('testimonials', [Admin\TestimonialController::class, 'index'])->name('testimonials.index');
    Route::post('testimonials', [Admin\TestimonialController::class, 'store'])->name('testimonials.store');
    Route::put('testimonials/{testimonial}', [Admin\TestimonialController::class, 'update'])->name('testimonials.update');
    Route::delete('testimonials/{testimonial}', [Admin\TestimonialController::class, 'destroy'])->name('testimonials.destroy');
    Route::get('sponsors', [Admin\SponsorController::class, 'index'])->name('sponsors.index');
    Route::post('sponsors', [Admin\SponsorController::class, 'store'])->name('sponsors.store');
    Route::put('sponsors/{sponsor}', [Admin\SponsorController::class, 'update'])->name('sponsors.update');
    Route::delete('sponsors/{sponsor}', [Admin\SponsorController::class, 'destroy'])->name('sponsors.destroy');
    Route::get('popups', [Admin\PopupController::class, 'index'])->name('popups.index');
    Route::post('popups', [Admin\PopupController::class, 'store'])->name('popups.store');
    Route::put('popups/{popup}', [Admin\PopupController::class, 'update'])->name('popups.update');
    Route::delete('popups/{popup}', [Admin\PopupController::class, 'destroy'])->name('popups.destroy');
    Route::get('subscribers', [Admin\SubscriberController::class, 'index'])->name('subscribers.index');
    Route::delete('subscribers/{subscriber}', [Admin\SubscriberController::class, 'destroy'])->name('subscribers.destroy');
    // Menus (M19) — items belong to a location's menu, which always exists.
    Route::get('menus', [Admin\MenuController::class, 'index'])->name('menus.index');
    Route::post('menus/{menu}/items', [Admin\MenuController::class, 'store'])->name('menus.items.store');
    Route::put('menus/{menu}/items/{item}', [Admin\MenuController::class, 'update'])->name('menus.items.update');
    Route::delete('menus/{menu}/items/{item}', [Admin\MenuController::class, 'destroy'])->name('menus.items.destroy');
    Route::post('menus/{menu}/reorder', [Admin\MenuController::class, 'reorder'])->name('menus.reorder');
    // CMS + language manager (M14) and the page builder (M20).
    Route::resource('pages', Admin\PageController::class)->except(['show']);
    Route::get('pages/{page}/blocks', [Admin\PageBlockController::class, 'index'])->name('pages.blocks.index');
    Route::post('pages/{page}/blocks', [Admin\PageBlockController::class, 'store'])->name('pages.blocks.store');
    Route::post('pages/{page}/blocks/reorder', [Admin\PageBlockController::class, 'reorder'])->name('pages.blocks.reorder');
    Route::put('pages/{page}/blocks/{block}', [Admin\PageBlockController::class, 'update'])->name('pages.blocks.update');
    Route::post('pages/{page}/blocks/{block}/duplicate', [Admin\PageBlockController::class, 'duplicate'])->name('pages.blocks.duplicate');
    Route::delete('pages/{page}/blocks/{block}', [Admin\PageBlockController::class, 'destroy'])->name('pages.blocks.destroy');
    Route::resource('faqs', Admin\FaqController::class)->except(['show']);
    // Blog (M21). Categories sit under the posts prefix but are their own
    // screen — `blog/categories` is declared before `blog/{post}`.
    Route::get('blog/categories', [Admin\BlogCategoryController::class, 'index'])->name('blog.categories.index');
    Route::post('blog/categories', [Admin\BlogCategoryController::class, 'store'])->name('blog.categories.store');
    Route::put('blog/categories/{category}', [Admin\BlogCategoryController::class, 'update'])->name('blog.categories.update');
    Route::delete('blog/categories/{category}', [Admin\BlogCategoryController::class, 'destroy'])->name('blog.categories.destroy');
    Route::get('blog', [Admin\BlogPostController::class, 'index'])->name('blog.index');
    Route::get('blog/create', [Admin\BlogPostController::class, 'create'])->name('blog.create');
    Route::post('blog', [Admin\BlogPostController::class, 'store'])->name('blog.store');
    Route::get('blog/{post}/edit', [Admin\BlogPostController::class, 'edit'])->name('blog.edit');
    Route::put('blog/{post}', [Admin\BlogPostController::class, 'update'])->name('blog.update');
    Route::delete('blog/{post}', [Admin\BlogPostController::class, 'destroy'])->name('blog.destroy');
    Route::get('languages', [Admin\LanguageController::class, 'index'])->name('languages.index');
    Route::post('languages', [Admin\LanguageController::class, 'store'])->name('languages.store');
    Route::put('languages/{language}', [Admin\LanguageController::class, 'update'])->name('languages.update');
    Route::delete('languages/{language}', [Admin\LanguageController::class, 'destroy'])->name('languages.destroy');
    Route::get('languages/{language}/translations', [Admin\LanguageController::class, 'editTranslations'])->name('languages.translations.edit');
    Route::put('languages/{language}/translations', [Admin\LanguageController::class, 'updateTranslations'])->name('languages.translations.update');
    // Support & helpdesk (M16).
    Route::get('tickets', [Admin\SupportTicketController::class, 'index'])->name('tickets.index');
    Route::get('tickets/{ticket}', [Admin\SupportTicketController::class, 'show'])->name('tickets.show');
    Route::post('tickets/{ticket}/reply', [Admin\SupportTicketController::class, 'reply'])->name('tickets.reply');
    Route::post('tickets/{ticket}/assign', [Admin\SupportTicketController::class, 'assign'])->name('tickets.assign');
    Route::post('tickets/{ticket}/resolve', [Admin\SupportTicketController::class, 'resolve'])->name('tickets.resolve');
    Route::post('tickets/{ticket}/close', [Admin\SupportTicketController::class, 'close'])->name('tickets.close');
    // Media library (M18). The picker endpoints answer JSON because the dialog
    // opens over a half-filled form — an Inertia visit would throw it away.
    Route::get('media', [Admin\MediaController::class, 'index'])->name('media.index');
    Route::post('media', [Admin\MediaController::class, 'store'])
        ->middleware('throttle:uploads')->name('media.store');
    Route::delete('media/{asset}', [Admin\MediaController::class, 'destroy'])->name('media.destroy');
    Route::get('media/picker', [Admin\MediaController::class, 'picker'])->name('media.picker');
    Route::post('media/picker', [Admin\MediaController::class, 'pickerStore'])
        ->middleware('throttle:uploads')->name('media.picker.store');

    Route::get('customers', [Admin\CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/{customer}', [Admin\CustomerController::class, 'show'])->name('customers.show');
    Route::post('customers/{customer}/block', [Admin\CustomerController::class, 'block'])->name('customers.block');
    Route::post('customers/{customer}/unblock', [Admin\CustomerController::class, 'unblock'])->name('customers.unblock');
    // Manual wallet correction (M22) — WalletService is still the only writer.
    Route::post('customers/{customer}/wallet', [Admin\CustomerController::class, 'adjustWallet'])->name('customers.wallet');

    // Communications (M23). Templates are an optional layer over the shipped
    // emails (D25) — deleting one restores the default, it never stops the mail.
    Route::get('email-templates', [Admin\EmailTemplateController::class, 'index'])->name('email-templates.index');
    Route::get('email-templates/{event}', [Admin\EmailTemplateController::class, 'edit'])->name('email-templates.edit');
    Route::put('email-templates/{event}', [Admin\EmailTemplateController::class, 'update'])->name('email-templates.update');
    Route::delete('email-templates/{event}', [Admin\EmailTemplateController::class, 'destroy'])->name('email-templates.destroy');
    // JSON, not Inertia: the preview fires while the admin types and an Inertia
    // visit would throw the half-written template away (M18's picker rule).
    Route::post('email-templates/{event}/preview', [Admin\EmailTemplateController::class, 'preview'])->name('email-templates.preview');
    Route::post('email-templates/{event}/test', [Admin\EmailTemplateController::class, 'test'])
        ->middleware('throttle:10,1')->name('email-templates.test');

    Route::get('notifications', [Admin\NotificationController::class, 'index'])->name('notifications.index');
    Route::put('notifications', [Admin\NotificationController::class, 'update'])->name('notifications.update');
    Route::post('notifications/announce', [Admin\NotificationController::class, 'announce'])
        ->middleware('throttle:5,1')->name('notifications.announce');

    // Settings is one screen per group (ADR D24): a save carries — and can
    // therefore only write — the keys of the group named in the URL.
    Route::get('settings', [Admin\SettingsController::class, 'index'])->name('settings.index');
    // Declared before settings/{group} or the wildcard swallows it (M21's feed).
    Route::post('settings/mail/test', [Admin\SettingsController::class, 'testMail'])
        ->middleware('throttle:10,1')->name('settings.mail.test');
    Route::get('settings/{group}', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings/{group}', [Admin\SettingsController::class, 'update'])->name('settings.update');

    // M24: install health, cron status and the browser's copy of `app:update`.
    Route::get('system', [Admin\SystemController::class, 'index'])->name('system.index');
    Route::post('system/update', [Admin\SystemController::class, 'update'])
        ->middleware('throttle:3,1')->name('system.update');

    Route::get('reports/{report}', [Admin\ReportController::class, 'show'])->name('reports.show');
    // GET so the CSV can stream straight to the browser as a download —
    // the queued branch just redirects back with a flash instead.
    Route::get('reports/{report}/export', [Admin\ReportController::class, 'export'])->name('reports.export');
    Route::get('exports/{file}', [Admin\ExportController::class, 'download'])->name('exports.download');
    Route::get('activity', [Admin\ActivityLogController::class, 'index'])->name('activity.index');
    Route::post('impersonate/{user}', [Admin\ImpersonationController::class, 'store'])->name('impersonate.store');
});

// Leaving impersonation happens while logged in AS the impersonated
// customer/provider, so this route cannot live in the admin group. The
// action 403s unless the session carries the impersonator key.
Route::delete('impersonate', [Admin\ImpersonationController::class, 'destroy'])
    ->middleware('auth')->name('impersonate.destroy');

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
