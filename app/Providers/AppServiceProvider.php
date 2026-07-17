<?php

namespace App\Providers;

use App\Domain\Comms\MailConfigurator;
use App\Domain\Comms\NotificationPreferences;
use App\Domain\Geocoding\NominatimGeocoder;
use App\Domain\Installer\EnvWriter;
use App\Domain\Installer\InstallLock;
use App\Domain\Media\StorageConfigurator;
use App\Domain\Settings\SettingsRegistry;
use App\Support\Geocoder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // FIRST. Not in boot(): `RateLimiter::for()` there resolves the limiter
        // singleton, and its factory reads `cache.default` once and memoizes the
        // store forever. Overriding the driver two lines later left the limiter
        // holding a *database* cache — so every throttled /install route queried
        // a `cache` table that the installer had not created yet, and the wizard
        // 500'd until you ran `php artisan migrate` by hand. Which is precisely
        // the thing a web installer exists to spare the buyer.
        $this->bootstrapInstallerEnvironment();

        $this->app->singleton(SettingsRegistry::class);
        // Memoizes the platform matrix: one dispatch fans out to many
        // notifications, and each one asks the same question (M23).
        $this->app->singleton(NotificationPreferences::class);
        $this->app->bind(Geocoder::class, NominatimGeocoder::class);
        // Resolvable so the installer's .env write is reachable from a test
        // pointed at a temp file. `base_path('.env')` is real, shared across
        // parallel workers, and belongs to whoever is running the suite.
        $this->app->bind(EnvWriter::class, fn (): EnvWriter => EnvWriter::forApp());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Inertia props: single resources and plain collections arrive
        // unwrapped; paginators keep their data/links/meta envelope.
        JsonResource::withoutWrapping();

        // P7.2. An N+1 is invisible on seeded data and fatal on real data: the
        // admin bookings list is fine with 12 rows and issues 300 queries with
        // 100. So the *tests* are made to fail on a lazy load — a sweep beats a
        // per-controller audit, because the query nobody looked at is the one
        // that bites. Off in production: a violation there must degrade to a
        // slow page, never to a 500 in front of a customer.
        Model::preventLazyLoading(! $this->app->isProduction());

        // The buyer's server is one mistyped command away from an empty
        // database, and `app:update` (M24) runs artisan from a browser button.
        // `migrate:fresh`, `migrate:refresh`, `db:wipe` now refuse to run in
        // production at all — there is no legitimate reason to want one there.
        DB::prohibitDestructiveCommands($this->app->isProduction());

        // Listeners in app/Listeners are auto-discovered from their handle()
        // type-hint — registering them here as well would fire each one twice.
        // Wiring today: BookingPlaced → DispatchPlacedBooking (M06);
        // BookingStatusChanged → BroadcastBookingStatus (M07) +
        // SendBookingStatusNotification (M11); BookingOffered →
        // NotifyProvidersOfOffer (M11). Verify with `php artisan event:list`.

        $this->defineRateLimiters();

        // SMTP is a settings row, not a .env line (M23): a buyer configures mail
        // from the browser. Nothing is forced when it is empty — the mailer keeps
        // whatever the environment said, and the `mail` channel simply never
        // joins a notification's via() (D14). A long-running queue worker reads
        // this at boot, so restart workers after changing SMTP.
        app(MailConfigurator::class)->apply();

        // Media storage rides the same idiom (D40): a fully configured
        // S3-compatible bucket becomes medialibrary's default disk; anything
        // less stays on the local public disk. Restart workers after changing
        // it — queued conversions read this at boot too.
        app(StorageConfigurator::class)->apply();
    }

    /**
     * Named rate limiters (P7.1).
     *
     * Three of them, because there are three kinds of abuse and they are counted
     * against different things:
     *
     * - `auth` is per IP. Account creation and password-reset mail are free for
     *   an attacker and expensive for us — one is a spam-account faucet, the
     *   other posts mail to an address the attacker does not own. Login keeps its
     *   own per-email lockout in `LoginRequest`; this is the wider net under it,
     *   loose enough that the lockout still fires first for a single account and
     *   tight enough to cap a spray across many.
     * - `uploads` is per user, because a file costs disk and image conversions,
     *   and the routes that take one are all authenticated.
     * - `public-write` is per IP: the guest-reachable POSTs (cart, city switch)
     *   write only to a session, but nothing authenticates them, so the only
     *   thing standing between them and a loop is this.
     *
     * `RouteRateLimitTest` sweeps for the routes that should carry one and don't.
     */
    private function defineRateLimiters(): void
    {
        RateLimiter::for('auth', fn (Request $request): Limit => Limit::perMinute(20)->by($request->ip() ?? 'unknown'));

        RateLimiter::for(
            'uploads',
            fn (Request $request): Limit => Limit::perMinute(30)
                ->by((string) (Auth::id() ?? $request->ip() ?? 'unknown')),
        );

        RateLimiter::for('public-write', fn (Request $request): Limit => Limit::perMinute(30)->by($request->ip() ?? 'unknown'));
    }

    /**
     * Keep the web installer (M15) reachable on a fresh upload.
     *
     * While `INSTALL=false` is in .env there is no database worth talking to —
     * the buyer has not typed its credentials yet — so nothing may need one:
     * session, cache and queue fall back to drivers that only want a writable
     * disk, and a missing APP_KEY is generated on the fly (cookie encryption
     * needs one before the wizard can render at all).
     *
     * The rule this enforces: a fresh install must reach every wizard screen
     * with zero commands run. Anything that reaches for the database before the
     * lock is gone is a bug, not a prerequisite.
     */
    private function bootstrapInstallerEnvironment(): void
    {
        if ($this->app->runningUnitTests() || InstallLock::installed()) {
            return;
        }

        config([
            'session.driver' => 'file',
            'cache.default' => 'file',
            'queue.default' => 'sync',
        ]);

        $key = config('app.key');
        if (($key === null || $key === '') && ! $this->app->runningInConsole()) {
            $generated = 'base64:'.base64_encode(random_bytes(32));

            try {
                EnvWriter::forApp()->write(['APP_KEY' => $generated]);
            } catch (RuntimeException) {
                // .env not writable — requirements screen will say so.
            }

            config(['app.key' => $generated]);
        }
    }
}
