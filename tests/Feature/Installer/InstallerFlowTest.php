<?php

use App\Domain\Installer\DatabaseProbe;
use App\Domain\Installer\EnvWriter;
use App\Domain\Settings\Enums\SettingType;
use App\Domain\Settings\SettingsRegistry;
use App\Domain\Users\Enums\Role;
use App\Models\User;
use Database\Seeders\DemoAccountSeeder;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('local');
});

/**
 * The wizard's database step, with the one thing a test cannot have — a real
 * MySQL on the other end — swapped out. Everything past the connect is the
 * code that actually writes the buyer's .env, and it is the half that shipped
 * broken because no test could reach it.
 */
function probeSucceeds(): void
{
    app()->instance(DatabaseProbe::class, new class extends DatabaseProbe
    {
        public function handle(string $host, int $port, string $database, string $username, string $password): void
        {
            // Reachable. The point of the step is what happens next.
        }
    });
}

/**
 * A throwaway .env for the wizard to write. Never base_path('.env'): that file
 * is real, it belongs to whoever is running the suite, and parallel workers
 * share it — the `lang/` and `bootstrap/cache` landmines, one directory up.
 */
function fakeEnv(string $contents = "INSTALL=false\nAPP_NAME=Laravel\n"): string
{
    $path = (string) tempnam(sys_get_temp_dir(), 'env');
    file_put_contents($path, $contents);
    app()->instance(EnvWriter::class, new EnvWriter($path));

    return $path;
}

/**
 * @return array<string, string>
 */
function envPairs(string $path): array
{
    $pairs = [];

    foreach (preg_split('/\R/', (string) file_get_contents($path)) ?: [] as $line) {
        if (preg_match('/^([A-Z0-9_]+)=(.*)$/', trim($line), $m) === 1) {
            $pairs[$m[1]] = trim($m[2], '"');
        }
    }

    return $pairs;
}

function notInstalled(): void
{
    config(['app.installed' => false]);
}

test('uninstalled app redirects everything to the installer', function () {
    notInstalled();

    $this->get('/')->assertRedirect(route('install.requirements'));
    $this->get('/login')->assertRedirect(route('install.requirements'));
});

test('installer requirements page renders when not installed', function () {
    notInstalled();

    $this->get('/install')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('installer/requirements')
            ->has('requirements.checks'));
});

test('installer is gone once installed', function () {
    // config('app.installed') is true via phpunit.xml
    $this->get('/install')->assertNotFound();
    $this->get('/install/database')->assertNotFound();

    // finish stays reachable so the last redirect after locking still lands
    $this->get('/install/finish')->assertOk();
});

test('database step rejects an unreachable database', function () {
    notInstalled();

    $this->from('/install/database')
        ->post('/install/database', [
            'app_name' => 'Acme',
            'app_url' => 'http://localhost',
            'host' => '127.0.0.1',
            'port' => 3399, // nothing listens here
            'database' => 'nope',
            'username' => 'nope',
            'password' => '',
        ])
        ->assertRedirect('/install/database')
        ->assertSessionHasErrors('database');
});

test('database step writes a deployable .env once the database answers', function () {
    notInstalled();
    probeSucceeds();
    $path = fakeEnv("INSTALL=false\nAPP_NAME=Laravel\nAPP_ENV=local\nAPP_DEBUG=true\n");

    $this->post('/install/database', [
        'app_name' => 'Acme Home Services',
        'app_url' => 'https://acme.example.com',
        'host' => 'db.internal',
        'port' => 3307,
        'database' => 'acme',
        'username' => 'acme_user',
        'password' => 's3cr#t pass',
    ])->assertRedirect(route('install.migrate'))->assertSessionHasNoErrors();

    $env = envPairs($path);

    // The whole point: every value the buyer typed reaches the file. `app_name`
    // is first because it is the one that did not — it had no validation rule,
    // so validated() dropped it and the step 500'd on the buyer's screen.
    expect($env['APP_NAME'])->toBe('Acme Home Services')
        ->and($env['APP_URL'])->toBe('https://acme.example.com')
        ->and($env['DB_HOST'])->toBe('db.internal')
        ->and($env['DB_PORT'])->toBe('3307')
        ->and($env['DB_DATABASE'])->toBe('acme')
        ->and($env['DB_USERNAME'])->toBe('acme_user')
        ->and($env['DB_PASSWORD'])->toBe('s3cr#t pass')
        // An install is a deployment, not a demo (P7.3).
        ->and($env['APP_ENV'])->toBe('production')
        ->and($env['APP_DEBUG'])->toBe('false')
        ->and($env['DB_CONNECTION'])->toBe('mysql')
        ->and($env['SESSION_DRIVER'])->toBe('database')
        ->and($env['QUEUE_CONNECTION'])->toBe('database')
        // The database step is not the finish line: the wizard has three screens
        // left and every one of them needs the installer still open.
        ->and($env)->toHaveKey('INSTALL');

    unlink($path);
});

test('an https app url secures the session cookie and the reverb the browser dials', function () {
    notInstalled();
    probeSucceeds();
    $path = fakeEnv();

    $this->post('/install/database', [
        'app_name' => 'Acme',
        'app_url' => 'https://acme.example.com',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'acme',
        'username' => 'root',
        'password' => '',
    ])->assertSessionHasNoErrors();

    $env = envPairs($path);

    // A session cookie that can travel over plain HTTP on an HTTPS site is one
    // an attacker can strip. The buyer never has to know this rule exists.
    expect($env['SESSION_SECURE_COOKIE'])->toBe('true')
        // What the browser dials is the site's own host through the proxy —
        // and it must never be the port reverb:start binds to (P7.3).
        ->and($env['REVERB_HOST'])->toBe('acme.example.com')
        ->and($env['REVERB_SCHEME'])->toBe('https')
        ->and($env['REVERB_PORT'])->toBe('443')
        ->and($env['REVERB_SERVER_HOST'])->toBe('0.0.0.0')
        ->and($env['REVERB_SERVER_PORT'])->toBe('8080')
        // Minted per install, which is exactly why the browser reads the key
        // from a shared prop and not from the prebuilt bundle.
        ->and($env['REVERB_APP_KEY'])->not->toBe('')
        ->and($env['REVERB_APP_SECRET'])->not->toBe('');

    unlink($path);
});

test('the database step requires every value it writes to .env', function () {
    notInstalled();
    probeSucceeds();

    // The regression pin. `app_name` had no rule, so the browser could send it
    // and validated() would still drop it — a required field that was not.
    $this->post('/install/database', [])
        ->assertSessionHasErrors(['app_name', 'app_url', 'host', 'port', 'database', 'username']);
});

test('admin step creates a verified admin and closes the installer', function () {
    notInstalled();
    $path = fakeEnv("INSTALL=false\nAPP_NAME=Acme\nDB_HOST=127.0.0.1\n");

    $response = $this->post('/install/admin', [
        'name' => 'Site Owner',
        'email' => 'owner@example.com',
        'password' => 'super-secret-1',
        'password_confirmation' => 'super-secret-1',
    ]);

    $response->assertRedirect(route('install.finish'));

    $user = User::where('email', 'owner@example.com')->firstOrFail();
    expect($user->hasRole('admin'))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull();

    $env = envPairs($path);

    // Deleting the line is the whole of "installed" now, so this assertion is
    // the gate: leave INSTALL behind and the wizard stays open forever on a
    // live site. Its neighbours must survive — removing a key is not licence
    // to rewrite the file the buyer's credentials live in.
    expect($env)->not->toHaveKey('INSTALL')
        ->and($env['APP_NAME'])->toBe('Acme')
        ->and($env['DB_HOST'])->toBe('127.0.0.1');

    unlink($path);
});

test('admin step validates input', function () {
    notInstalled();
    $path = fakeEnv();

    $this->post('/install/admin', [
        'name' => '',
        'email' => 'not-an-email',
        'password' => 'short',
        'password_confirmation' => 'different',
    ])->assertSessionHasErrors(['name', 'email', 'password']);

    // A refused form must not close the installer.
    expect(envPairs($path))->toHaveKey('INSTALL');

    unlink($path);
});

test('the admin step refuses to mint an admin over an already-installed database', function () {
    notInstalled();
    $path = fakeEnv();

    // The scenario: .env is restored from the example on a site that is already
    // trading, so INSTALL=false is back and the wizard is open over real data.
    app(SettingsRegistry::class)->set('system.installed_at', now()->toIso8601String(), SettingType::String, 'system');

    $this->post('/install/admin', [
        'name' => 'Intruder',
        'email' => 'intruder@example.com',
        'password' => 'super-secret-1',
        'password_confirmation' => 'super-secret-1',
    ])->assertSessionHasErrors('email');

    expect(User::where('email', 'intruder@example.com')->exists())->toBeFalse()
        // And it must not quietly close the installer either — the operator is
        // told to remove the line themselves, deliberately.
        ->and(envPairs($path))->toHaveKey('INSTALL');

    unlink($path);
});

test('seeded demo data does not count as an existing install', function () {
    notInstalled();
    $path = fakeEnv();

    // The trap the first version of that guard fell into: it asked "does an
    // admin exist", and ticking *install demo data* seeds `Demo Admin` one step
    // earlier. Every demo install would have been refused its own owner account.
    $this->seed(DemoAccountSeeder::class);
    expect(User::role(Role::Admin->value)->exists())->toBeTrue();

    $this->post('/install/admin', [
        'name' => 'Site Owner',
        'email' => 'owner@example.com',
        'password' => 'super-secret-1',
        'password_confirmation' => 'super-secret-1',
    ])->assertRedirect(route('install.finish'))->assertSessionHasNoErrors();

    expect(envPairs($path))->not->toHaveKey('INSTALL');

    unlink($path);
});

test('migrate step page renders and seeding runs', function () {
    notInstalled();

    $this->get('/install/migrate')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('installer/migrate'));

    // sqlite test database is already migrated; the step is idempotent.
    $this->post('/install/migrate', ['demo' => false])
        ->assertRedirect(route('install.admin'));
});
