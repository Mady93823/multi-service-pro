<?php

use App\Domain\Security\Recaptcha;
use App\Domain\Settings\SettingsRegistry;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\Support\SettingsFixtures;

/**
 * M24: reCaptcha must be invisible on a fresh install (no keys, no forms
 * blocked) and must **fail open** when Google is unreachable — a CAPTCHA outage
 * is not allowed to become a registration outage.
 */
function enableRecaptcha(string ...$forms): void
{
    $settings = app(SettingsRegistry::class);
    $settings->set('recaptcha.site_key', 'site-key');
    $settings->set('recaptcha.secret_key', 'secret-key');

    foreach ($forms as $form) {
        $settings->set('recaptcha.on_'.$form, true);
    }
}

function registerPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Asha',
        'email' => 'asha@example.test',
        'password' => 'password-1234',
        'password_confirmation' => 'password-1234',
    ], $overrides);
}

test('with no keys the forms behave exactly as before', function () {
    Http::fake();

    $this->post(route('register'), registerPayload())->assertRedirect();

    expect(User::query()->where('email', 'asha@example.test')->exists())->toBeTrue();

    // Nothing was asked of Google, because Google is not part of this install.
    Http::assertNothingSent();
});

test('keys but no form ticked still asks nobody to prove anything', function () {
    Http::fake();

    $settings = app(SettingsRegistry::class);
    $settings->set('recaptcha.site_key', 'site-key');
    $settings->set('recaptcha.secret_key', 'secret-key');

    $this->post(route('register'), registerPayload())->assertRedirect();

    Http::assertNothingSent();
});

test('a protected form refuses a request with no token', function () {
    Http::fake();
    enableRecaptcha('register');

    $this->post(route('register'), registerPayload())->assertSessionHasErrors('recaptcha_token');

    expect(User::query()->where('email', 'asha@example.test')->exists())->toBeFalse();
});

test('a good token gets through', function () {
    enableRecaptcha('register');

    Http::fake(['www.google.com/*' => Http::response(['success' => true, 'score' => 0.9])]);

    $this->post(route('register'), registerPayload(['recaptcha_token' => 'token']))->assertRedirect();

    expect(User::query()->where('email', 'asha@example.test')->exists())->toBeTrue();
});

test('a low score is refused', function () {
    enableRecaptcha('register');

    // v3 always "succeeds" — the score is the verdict.
    Http::fake(['www.google.com/*' => Http::response(['success' => true, 'score' => 0.1])]);

    $this->post(route('register'), registerPayload(['recaptcha_token' => 'token']))
        ->assertSessionHasErrors('recaptcha_token');

    expect(User::query()->where('email', 'asha@example.test')->exists())->toBeFalse();
});

test('an unreachable Google lets the visitor through', function () {
    enableRecaptcha('register');

    // A CAPTCHA outage must never become a signup outage — the same doctrine as
    // a dead Reverb (M07) and a dead SMS gateway (M23).
    Http::fake(['www.google.com/*' => Http::response('down', 500)]);

    $this->post(route('register'), registerPayload(['recaptcha_token' => 'token']))->assertRedirect();

    expect(User::query()->where('email', 'asha@example.test')->exists())->toBeTrue();
});

test('the contact form is protected independently of the others', function () {
    Http::fake(['www.google.com/*' => Http::response(['success' => false])]);

    enableRecaptcha('contact');

    $this->post(route('contact.store'), [
        'name' => 'Asha',
        'email' => 'asha@example.test',
        'subject' => 'Hello',
        'message' => 'Is anyone there?',
        'recaptcha_token' => 'token',
    ])->assertSessionHasErrors('recaptcha_token');

    // Registration was never ticked, so it stays open.
    Http::fake();
    $this->post(route('register'), registerPayload())->assertRedirect();
});

test('only the site key ever reaches the browser', function () {
    enableRecaptcha('login');

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('recaptcha.site_key', 'site-key')
            ->where('recaptcha.forms.login', true))
        ->assertDontSee('secret-key');
});

test('the secret key is write-only on the settings screen', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.settings.update', 'recaptcha'), SettingsFixtures::payload('recaptcha', [
            'site_key' => 'site-key',
            'secret_key' => 'top-secret',
            'on_register' => true,
        ]))
        ->assertRedirect();

    expect(app(Recaptcha::class)->isConfigured())->toBeTrue();

    $this->actingAs($admin)
        ->get(route('admin.settings.edit', 'recaptcha'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('values.secret_key_set', true)->missing('values.secret_key'))
        ->assertDontSee('top-secret');
});
