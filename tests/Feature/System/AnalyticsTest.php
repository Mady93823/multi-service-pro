<?php

use App\Domain\Settings\SettingsRegistry;
use App\Models\User;
use Tests\Support\SettingsFixtures;

/**
 * M24: analytics are *ids*, not snippets (D26's rule — the common case must
 * never need the site-wide script-execution permission), and they are storefront
 * only.
 */
test('an install with no ids ships no analytics prop at all', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('site.analytics', null));
});

test('the ids reach the storefront but never the admin panel', function () {
    app(SettingsRegistry::class)->set('analytics.ga4_id', 'G-ABC12345');

    $this->get('/')
        ->assertInertia(fn ($page) => $page->where('site.analytics.ga4_id', 'G-ABC12345'));

    // A tracking tag inside the admin panel measures the operator, not the market.
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where('site.analytics', null));
});

test('only a real measurement id is accepted', function () {
    $admin = User::factory()->admin()->create();

    // The value is interpolated into a script we ship, so it may only be an id.
    $this->actingAs($admin)
        ->put(route('admin.settings.update', 'analytics'), SettingsFixtures::payload('analytics', [
            'ga4_id' => "G-1');alert(1);//",
        ]))
        ->assertSessionHasErrors('ga4_id');

    $this->actingAs($admin)
        ->put(route('admin.settings.update', 'analytics'), SettingsFixtures::payload('analytics', [
            'ga4_id' => 'G-ABC12345',
            'gtm_id' => 'GTM-ABC1234',
            'meta_pixel_id' => '123456789012',
        ]))
        ->assertRedirect();

    expect(app(SettingsRegistry::class)->string('analytics.gtm_id'))->toBe('GTM-ABC1234');
});
