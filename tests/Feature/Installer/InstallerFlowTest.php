<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('local');
});

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

test('admin step creates a verified admin and writes the lock', function () {
    notInstalled();

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

    Storage::disk('local')->assertExists('installed.lock');
});

test('admin step validates input', function () {
    notInstalled();

    $this->post('/install/admin', [
        'name' => '',
        'email' => 'not-an-email',
        'password' => 'short',
        'password_confirmation' => 'different',
    ])->assertSessionHasErrors(['name', 'email', 'password']);

    Storage::disk('local')->assertMissing('installed.lock');
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
