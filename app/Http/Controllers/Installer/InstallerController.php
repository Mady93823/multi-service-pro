<?php

namespace App\Http\Controllers\Installer;

use App\Domain\Installer\EnvWriter;
use App\Domain\Installer\InstallLock;
use App\Domain\Installer\RequirementsChecker;
use App\Domain\Users\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Installer\StoreAdminRequest;
use App\Http\Requests\Installer\StoreDatabaseRequest;
use App\Models\User;
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
            'REVERB_APP_ID' => random_int(100000, 999999),
            'REVERB_APP_KEY' => Str::lower(Str::random(20)),
            'REVERB_APP_SECRET' => Str::lower(Str::random(24)),
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
            Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder', '--force' => true]);
            Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\SettingsSeeder', '--force' => true]);

            if ($data['demo'] ?? false) {
                Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\DemoAccountSeeder', '--force' => true]);
                Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder', '--force' => true]);
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

    public function finish(): Response
    {
        return Inertia::render('installer/finish');
    }
}
