<?php

namespace App\Http\Controllers\Installer;

use App\Domain\Installer\DeploymentGuide;
use App\Domain\Installer\EnvWriter;
use App\Domain\Installer\InstallLock;
use App\Domain\Installer\RequirementsChecker;
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
use PDO;
use PDOException;

class InstallerController extends Controller
{
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

    public function storeDatabase(StoreDatabaseRequest $request): RedirectResponse
    {
        /** @var array{app_name: string, app_url: string, host: string, port: int|string, database: string, username: string, password?: string|null} $data */
        $data = $request->validated();
        $password = $data['password'] ?? '';

        try {
            new PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s', $data['host'], (int) $data['port'], $data['database']),
                $data['username'],
                $password,
                [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            );
        } catch (PDOException $e) {
            throw ValidationException::withMessages([
                'database' => __('Could not connect: :message', ['message' => $e->getMessage()]),
            ]);
        }

        $secure = str_starts_with(Str::lower($data['app_url']), 'https://');

        EnvWriter::forApp()->write([
            'APP_NAME' => $data['app_name'],
            'APP_URL' => $data['app_url'],
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $data['host'],
            'DB_PORT' => (int) $data['port'],
            'DB_DATABASE' => $data['database'],
            'DB_USERNAME' => $data['username'],
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
            'REVERB_HOST' => (string) parse_url($data['app_url'], PHP_URL_HOST),
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function admin(): Response|RedirectResponse
    {
        if (! Schema::hasTable('users')) {
            return redirect()->route('install.migrate');
        }

        return Inertia::render('installer/admin');
    }

    public function storeAdmin(StoreAdminRequest $request): RedirectResponse
    {
        /** @var array{name: string, email: string, password: string} $data */
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole(Role::Admin->value);

        InstallLock::write();

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
