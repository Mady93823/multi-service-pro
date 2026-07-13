<?php

use App\Domain\Settings\SettingsRegistry;
use App\Models\ActivityLog;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Tests\Support\SettingsFixtures;

/**
 * The storefront chrome shared on every request (M19): appearance, social links,
 * the cookie banner and — the one with teeth — custom CSS/JS (ADR D26).
 */
function siteAdmin(): User
{
    return User::factory()->admin()->create();
}

it('shares appearance and social links with the storefront', function () {
    $settings = app(SettingsRegistry::class);
    $settings->set('appearance.header_variant', 'centered');
    $settings->set('appearance.contact_email', 'help@acme.test');
    $settings->set('social.instagram', 'https://instagram.com/acme');

    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia
            ->where('site.appearance.header_variant', 'centered')
            ->where('site.appearance.contact_email', 'help@acme.test')
            ->where('site.social.instagram', 'https://instagram.com/acme'));
});

it('keeps the cookie banner off until it is configured', function () {
    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia->where('site.cookie', null));

    $settings = app(SettingsRegistry::class);
    $settings->set('cookie.enabled', true);
    $settings->set('cookie.message', 'We use cookies.');
    $settings->set('cookie.accept_label', 'Got it');

    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia
            ->where('site.cookie.message', 'We use cookies.')
            ->where('site.cookie.accept_label', 'Got it'));
});

it('never injects custom code while the switch is off', function () {
    app(SettingsRegistry::class)->set('custom_code.css', 'body{display:none}');

    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia->where('site.custom_code', null));
});

it('injects custom code into the storefront and never into the admin panel', function () {
    $settings = app(SettingsRegistry::class);
    $settings->set('custom_code.enabled', true);
    $settings->set('custom_code.js', 'window.acme = 1');

    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia->where('site.custom_code.js', 'window.acme = 1'));

    // The panel that lets an admin *remove* a broken snippet must never run it.
    $this->actingAs(siteAdmin())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia->where('site.custom_code', null));
});

it('saves custom code and logs the save without ever logging the code', function () {
    $admin = siteAdmin();

    $this->actingAs($admin)
        ->put(route('admin.settings.update', 'custom_code'), SettingsFixtures::payload('custom_code', [
            'enabled' => true,
            'css' => '.brand{color:red}',
        ]))
        ->assertRedirect();

    expect(app(SettingsRegistry::class)->string('custom_code.css'))->toBe('.brand{color:red}');

    $log = ActivityLog::query()->where('actor_id', $admin->id)->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->context['group'] ?? null)->toBe('custom_code');

    // Settings saves log keys, never values — and this group's value *is* code.
    $this->assertStringNotContainsString('color:red', json_encode($log->context) ?: '');
});

it('cannot write a key that belongs to another settings group', function () {
    $this->actingAs(siteAdmin())
        ->put(route('admin.settings.update', 'social'), SettingsFixtures::payload('social', [
            'custom_code_enabled' => true,
            'enabled' => true,
        ]))
        ->assertRedirect();

    expect(app(SettingsRegistry::class)->boolean('custom_code.enabled'))->toBeFalse();
});
