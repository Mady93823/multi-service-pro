<?php

namespace App\Http\Controllers\Installer;

use App\Domain\Installer\DatabaseProbe;
use App\Domain\Installer\DeploymentGuide;
use App\Domain\Installer\EnvWriter;
use App\Domain\Installer\InstallLock;
use App\Domain\Installer\RequirementsChecker;
use App\Domain\Settings\Enums\SettingType;
use App\Domain\Settings\SettingsRegistry;
use App\Domain\Users\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Installer\StoreAdminRequest;
use App\Http\Requests\Installer\StoreDatabaseRequest;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\CitySeeder;
use Database\Seeders\CmsSeeder;
use Database\Seeders\DemoAccountSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\ZoneSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PDOException;
use Throwable;

class InstallerController extends Controller
{
    /**
     * Machine state, not a setting: it has no screen, no default and no group.
     * Same shape as `system.scheduler_last_run` (M24), and deliberately outside
     * `SettingsRegistry::defaults()` for the same reason — every default key must
     * be owned by a settings group, and nobody should ever edit this one.
     */
    private const INSTALLED_AT = 'system.installed_at';

    public function __construct(private readonly SettingsRegistry $settings) {}

    /**
     * Seeded on every install. `InstallerSeedTest` asserts this list stays in
     * step with what a working site actually needs — a module that adds a
     * load-bearing row and forgets the wizard is a bug only a fresh VPS finds.
     *
     * @var list<class-string>
     */
    private const BASE_SEEDERS = [
        RoleSeeder::class,
        SettingsSeeder::class,
        CmsSeeder::class,
    ];

    /** @var list<class-string> */
    private const DEMO_SEEDERS = [
        DemoAccountSeeder::class,
        CatalogSeeder::class,
        CitySeeder::class,
        ZoneSeeder::class,
    ];

    public function requirements(RequirementsChecker $checker): Response
    {
        return Inertia::render('installer/requirements', [
            'requirements' => $checker->run(),
        ]);
    }

    public function database(Request $request): Response
    {
        return Inertia::render('installer/database', [
            'defaults' => [
                'app_name' => (string) config('app.name'),
                'app_url' => $request->getSchemeAndHttpHost(),
                'host' => '127.0.0.1',
                'port' => 3306,
            ],
        ]);
    }

    public function storeDatabase(StoreDatabaseRequest $request, DatabaseProbe $probe, EnvWriter $env): RedirectResponse
    {
        // safe() rather than a hand-written array{} shape over validated(). The
        // shape was the bug: it promised `app_name`, the rules never asked for
        // one, so validated() dropped it — and PHPStan believed the docblock over
        // the FormRequest all the way onto a buyer's screen. safe() still reads
        // validated data only (an unruled key stays unreadable, which is the
        // point) but it is typed, so no annotation has to be kept honest by hand.
        $valid = $request->safe();

        $appName = $valid->string('app_name')->toString();
        $appUrl = $valid->string('app_url')->toString();
        $host = $valid->string('host')->toString();
        $port = $valid->integer('port');
        $database = $valid->string('database')->toString();
        $username = $valid->string('username')->toString();
        $password = $valid->string('password')->toString();

        try {
            $probe->handle($host, $port, $database, $username, $password);
        } catch (PDOException $e) {
            throw ValidationException::withMessages([
                'database' => __('Could not connect: :message', ['message' => $e->getMessage()]),
            ]);
        }

        $secure = str_starts_with(Str::lower($appUrl), 'https://');

        $env->write([
            'APP_NAME' => $appName,
            'APP_URL' => $appUrl,
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $host,
            'DB_PORT' => $port,
            'DB_DATABASE' => $database,
            'DB_USERNAME' => $username,
            'DB_PASSWORD' => $password,

            // A session cookie that can travel over plain HTTP on an HTTPS site
            // is a session cookie an attacker can strip. The wizard knows the
            // scheme — the buyer should not have to know this rule exists (P7.3).
            'SESSION_SECURE_COOKIE' => $secure ? 'true' : 'false',

            // The install is a real deployment, not a demo: durable session,
            // cache and queue, so a restart does not log everyone out and a
            // notification is not sent inside the customer's own request.
            'SESSION_DRIVER' => 'database',
            'CACHE_STORE' => 'database',
            'QUEUE_CONNECTION' => 'database',

            // Realtime. The app id/key/secret are minted per install — which is
            // exactly why the browser reads the key from the response and not
            // from the prebuilt bundle (P7.3, ReverbConfig).
            'BROADCAST_CONNECTION' => 'reverb',
            'REVERB_APP_ID' => random_int(100000, 999999),
            'REVERB_APP_KEY' => Str::lower(Str::random(20)),
            'REVERB_APP_SECRET' => Str::lower(Str::random(24)),
            // What the *browser* dials: the site's own host, through the proxy.
            'REVERB_HOST' => (string) parse_url($appUrl, PHP_URL_HOST),
            'REVERB_PORT' => $secure ? 443 : 8080,
            'REVERB_SCHEME' => $secure ? 'https' : 'http',
            // What the reverb:start process binds to, behind that proxy.
            'REVERB_SERVER_HOST' => '0.0.0.0',
            'REVERB_SERVER_PORT' => 8080,
        ]);

        Artisan::call('config:clear');

        return redirect()->route('install.migrate');
    }

    public function migrate(): Response
    {
        return Inertia::render('installer/migrate', [
            'database' => (string) config('database.connections.mysql.database'),
        ]);
    }

    public function runMigrate(Request $request): RedirectResponse
    {
        /** @var array{demo?: bool} $data */
        $data = $request->validate(['demo' => ['boolean']]);

        try {
            Artisan::call('migrate', ['--force' => true]);

            // The floor of a working install, not a demo of one: the roles, the
            // settings defaults — and the CMS, because `lang/en.json` needs its
            // `languages` row, the footer needs its legal pages, and the home
            // page IS a page (M20). A site with no `home` row still renders (the
            // block reader falls back), but the buyer would then have nothing to
            // edit and no way to discover that the home page is editable at all.
            foreach (self::BASE_SEEDERS as $seeder) {
                Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);
            }

            if ($data['demo'] ?? false) {
                foreach (self::DEMO_SEEDERS as $seeder) {
                    Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);
                }
            }
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'migrate' => __('Migration failed: :message', ['message' => $e->getMessage()]),
            ]);
        }

        $this->linkStorage();

        return redirect()->route('install.admin');
    }

    /**
     * Publish public/storage. Uploaded images (banners, media library, branding
     * logo) are served from there — without the link every image on the site 404s.
     * Never fatal: some shared hosts forbid symlinks, and the install must still
     * finish (the requirements step reports the missing link).
     */
    private function linkStorage(): void
    {
        if (file_exists(public_path('storage'))) {
            return;
        }

        try {
            Artisan::call('storage:link');
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function admin(): Response|RedirectResponse
    {
        // Asking a database that has not been configured yet whether it has a
        // users table does not fail — it *blocks*, until PHP gives up. A buyer
        // who refreshes this step, bookmarks it or comes back to it before the
        // database step gets a hung tab and nothing to read. No table and no
        // database are the same answer here: you are not ready for this screen.
        try {
            $ready = Schema::hasTable('users');
        } catch (Throwable) {
            return redirect()->route('install.database');
        }

        return $ready
            ? Inertia::render('installer/admin')
            : redirect()->route('install.migrate');
    }

    public function storeAdmin(StoreAdminRequest $request, EnvWriter $env): RedirectResponse
    {
        $valid = $request->safe();

        // Minting an admin is the one irreversible thing the wizard does, and the
        // flag that guards it now lives in .env — a file a buyer can restore from
        // backup or overwrite from the example by accident. Put INSTALL=false back
        // on a trading site and this step would hand a stranger the business.
        //
        // So it asks the *database* whether it has been installed before, which no
        // .env mishap can answer wrongly. Not "does an admin exist": ticking demo
        // data seeds one, and that must stay a legitimate first install.
        if ($this->settings->string(self::INSTALLED_AT) !== '') {
            throw ValidationException::withMessages([
                'email' => __('This site is already installed. Remove the INSTALL line from your .env file to close the installer.'),
            ]);
        }

        $user = User::create([
            'name' => $valid->string('name')->toString(),
            'email' => $valid->string('email')->toString(),
            'password' => Hash::make($valid->string('password')->toString()),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole(Role::Admin->value);

        // Stamped before the flag is dropped: if the .env write fails, the wizard
        // stays open but the database already says it is installed — so the retry
        // is refused and the operator is told to remove the line by hand. Better a
        // stuck install the operator can see than a wizard that can be reopened.
        $this->settings->set(self::INSTALLED_AT, now()->toIso8601String(), SettingType::String, 'system');

        InstallLock::write($env);

        return redirect()->route('install.finish');
    }

    public function finish(DeploymentGuide $guide): Response
    {
        // The wizard's last screen is the one that decides whether the install is
        // actually alive: cron, a queue worker and Reverb are all invisible when
        // missing — every page still loads, and only the things that matter stop.
        return Inertia::render('installer/finish', [
            'deployment' => $guide->handle(),
        ]);
    }
}
