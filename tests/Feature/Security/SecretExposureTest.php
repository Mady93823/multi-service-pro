<?php

use App\Domain\Settings\SettingsGroupRegistry;
use App\Domain\Settings\SettingsRegistry;
use App\Models\User;

/**
 * Inertia serializes every prop into the page's HTML. A settings screen that
 * hands the current value back to the form — the obvious way to build an edit
 * screen — therefore prints the gateway secret into the document, where a stored
 * XSS, a browser extension or a screen-share reads it.
 *
 * Since M08 the rule has been: credentials are write-only, the screen gets a
 * `*_set` boolean. Every group since has had to remember it. This sweep is what
 * makes remembering unnecessary — it derives the secret list from the key names
 * themselves, so a group added tomorrow is covered without being told about.
 */
const SENTINEL = 'sentinel-value-that-must-never-render';

/** @return list<string> */
function secretSettingKeys(): array
{
    return array_values(array_filter(
        array_keys(SettingsRegistry::defaults()),
        // A publishable key, a site key and a GA4 id are *supposed* to reach the
        // browser — they are public halves. These are the private ones.
        // `salt` entered with PayU (D39): its merchant salt signs every hash.
        fn (string $key): bool => preg_match('/(secret|password|_token|auth_key|credentials|salt)/i', $key) === 1,
    ));
}

test('the secret list is the one we think it is', function () {
    // Assert the negative: a regex that matched nothing would make every test
    // below pass while proving nothing at all.
    expect(secretSettingKeys())->toEqualCanonicalizing([
        'payments.razorpay_key_secret',
        'payments.razorpay_webhook_secret',
        'payments.stripe_secret_key',
        'payments.stripe_webhook_secret',
        'payments.payu_salt',
        'payments.paypal_client_secret',
        'mail.password',
        'sms.msg91_auth_key',
        'sms.twilio_token',
        'integrations.fcm_credentials',
        'recaptcha.secret_key',
    ]);
});

test('no settings screen renders a stored credential', function () {
    $settings = app(SettingsRegistry::class);

    foreach (secretSettingKeys() as $key) {
        $settings->set($key, SENTINEL);
    }

    $admin = User::factory()->admin()->create();

    foreach (array_keys(app(SettingsGroupRegistry::class)->all()) as $group) {
        $response = $this->actingAs($admin)->get(route('admin.settings.edit', $group));

        $response->assertOk();

        expect($response->getContent())->not->toContain(
            SENTINEL,
            "The {$group} settings screen serialized a credential into the page.",
        );
    }
});

test('the write-only screens still say a credential is set', function () {
    // The other half of the contract: an admin must be able to tell "configured"
    // from "empty" without being shown the value.
    $settings = app(SettingsRegistry::class);
    $settings->set('payments.razorpay_key_secret', SENTINEL);
    $settings->set('payments.razorpay_key_id', 'rzp_live_public');

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.settings.edit', 'payments'))
        ->assertInertia(fn ($page) => $page
            ->where('values.razorpay_key_secret_set', true)
            // The public half is not a secret and must keep rendering, or the
            // checkout page has no key to open the gateway with.
            ->where('values.razorpay_key_id', 'rzp_live_public'));
});

test('the public halves of the credential pairs are not treated as secrets', function () {
    expect(secretSettingKeys())
        ->not->toContain('payments.razorpay_key_id')
        ->not->toContain('payments.stripe_publishable_key')
        ->not->toContain('payments.payu_key')
        ->not->toContain('payments.paypal_client_id')
        ->not->toContain('recaptcha.site_key')
        ->not->toContain('integrations.google_maps_key');
});
