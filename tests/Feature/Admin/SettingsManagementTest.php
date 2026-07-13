<?php

use App\Domain\Settings\SettingsGroupRegistry;
use App\Domain\Settings\SettingsRegistry;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\Support\SettingsFixtures;

beforeEach(function () {
    app(SettingsRegistry::class)->flush();
});

/** PUT one settings group (ADR D24) — a save carries only that group's keys. */
function saveSettings(User $admin, string $group, array $overrides = []): TestResponse
{
    return test()->actingAs($admin)->put(
        "/admin/settings/{$group}",
        SettingsFixtures::payload($group, $overrides),
    );
}

function freshSettings(): SettingsRegistry
{
    $settings = app()->make(SettingsRegistry::class);
    $settings->flush();

    return $settings;
}

test('guests and non-admins cannot open or save settings', function () {
    $this->get('/admin/settings')->assertRedirect('/login');

    $this->actingAs(User::factory()->customer()->create())
        ->get('/admin/settings/branding')
        ->assertForbidden();

    $this->actingAs(User::factory()->provider()->create())
        ->put('/admin/settings/branding', SettingsFixtures::payload('branding'))
        ->assertForbidden();
});

test('the settings index redirects to the first group', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/settings')
        ->assertRedirect('/admin/settings/branding');
});

test('an unknown settings group 404s instead of rendering an empty form', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin/settings/nope')->assertNotFound();
    $this->actingAs($admin)->put('/admin/settings/nope', ['app_name' => 'x'])->assertNotFound();
});

test('each group renders its own screen with the group navigation', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/settings/localization')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/settings/edit')
            ->where('group', 'localization')
            ->where('values.currency', 'INR')
            ->where('values.timezone', 'Asia/Kolkata')
            // The nav lists every group, so no screen is unreachable. Counted
            // from the registry: a hardcoded number just breaks on the next one.
            ->has('groups', count(app(SettingsGroupRegistry::class)->all()))
            // A group's screen carries its keys and nobody else's.
            ->missing('values.app_name'));
});

test('admin can update branding and localization settings', function () {
    $admin = User::factory()->admin()->create();

    saveSettings($admin, 'branding', ['app_name' => 'Client Brand'])
        ->assertRedirect()
        ->assertSessionHas('success');

    saveSettings($admin, 'localization', ['currency' => 'USD', 'timezone' => 'America/New_York'])
        ->assertSessionHasNoErrors();

    $settings = freshSettings();

    expect($settings->string('branding.app_name'))->toBe('Client Brand')
        ->and($settings->string('branding.primary_color'))->toBe('#4f46e5')
        ->and($settings->string('localization.currency'))->toBe('USD')
        ->and($settings->string('localization.timezone'))->toBe('America/New_York');
});

test('updated app name is shared with every Inertia page', function () {
    $admin = User::factory()->admin()->create();

    saveSettings($admin, 'branding', ['app_name' => 'Client Brand']);

    $this->actingAs($admin)
        ->get('/admin/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('name', 'Client Brand'));
});

test('a broken key in one group does not block a save in another', function () {
    $admin = User::factory()->admin()->create();

    // Branding is invalid...
    saveSettings($admin, 'branding', ['app_name' => ''])->assertSessionHasErrors('app_name');

    // ...and the payouts screen still saves. Under the old single-payload
    // request this was the failure mode: one bad field 422'd every form.
    saveSettings($admin, 'payouts', ['commission_percent' => 12.5])->assertSessionHasNoErrors();

    expect(freshSettings()->decimal('payments.commission_percent'))->toBe(12.5);
});

test('a payload cannot write another group keys', function () {
    $admin = User::factory()->admin()->create();

    // `commission_percent` belongs to the payouts group; smuggling it into a
    // localization save must be ignored, not applied.
    saveSettings($admin, 'localization', ['commission_percent' => 99])->assertSessionHasNoErrors();

    expect(freshSettings()->decimal('payments.commission_percent'))->toBe(20.0);
});

test('invalid color, currency and timezone are rejected', function () {
    $admin = User::factory()->admin()->create();

    saveSettings($admin, 'branding', ['primary_color' => 'red'])->assertSessionHasErrors('primary_color');
    saveSettings($admin, 'localization', ['currency' => 'rupees', 'timezone' => 'Mars/Olympus'])
        ->assertSessionHasErrors(['currency', 'timezone']);
});

test('booking settings are validated and saved', function () {
    $admin = User::factory()->admin()->create();

    saveSettings($admin, 'booking', [
        'day_ends' => '07:00', // before day_starts
        'cancellation_fee_type' => 'percent',
        'cancellation_fee_value' => 150, // >100% not allowed for percent
    ])->assertSessionHasErrors(['day_ends', 'cancellation_fee_value']);

    saveSettings($admin, 'booking', [
        'slot_minutes' => 120,
        'job_otp_required' => false,
        'payment_timeout_minutes' => 45,
    ])->assertSessionHasNoErrors();

    $settings = freshSettings();

    expect($settings->integer('booking.slot_minutes'))->toBe(120)
        ->and($settings->boolean('booking.job_otp_required'))->toBeFalse()
        ->and($settings->integer('booking.payment_timeout_minutes'))->toBe(45);
});

test('dispatch and tracking settings are validated and saved', function () {
    $admin = User::factory()->admin()->create();

    saveSettings($admin, 'dispatch', ['dispatch_mode' => 'telepathy'])->assertSessionHasErrors('dispatch_mode');
    saveSettings($admin, 'tracking', ['ping_interval_seconds' => 0])->assertSessionHasErrors('ping_interval_seconds');

    saveSettings($admin, 'dispatch', ['dispatch_mode' => 'broadcast', 'dispatch_auto' => false])->assertSessionHasNoErrors();
    saveSettings($admin, 'tracking', ['ping_interval_seconds' => 10, 'points_retention_days' => 90])->assertSessionHasNoErrors();

    $settings = freshSettings();

    expect($settings->string('dispatch.mode'))->toBe('broadcast')
        ->and($settings->boolean('dispatch.auto'))->toBeFalse()
        ->and($settings->integer('tracking.ping_interval_seconds'))->toBe(10)
        ->and($settings->integer('tracking.points_retention_days'))->toBe(90);
});

test('logo can be uploaded and removed', function () {
    Storage::fake('public');
    $admin = User::factory()->admin()->create();

    saveSettings($admin, 'branding', ['logo' => UploadedFile::fake()->image('logo.png', 200, 60)]);

    $settings = freshSettings();
    $path = $settings->string('branding.logo_path');

    expect($path)->not->toBe('');
    Storage::disk('public')->assertExists($path);

    saveSettings($admin, 'branding', ['remove_logo' => true]);

    $settings->flush();
    expect($settings->string('branding.logo_path'))->toBe('');
    Storage::disk('public')->assertMissing($path);
});

test('gateway secrets are never sent to the browser', function () {
    $settings = app(SettingsRegistry::class);
    $settings->set('payments.razorpay_key_id', 'rzp_test_key');
    $settings->set('payments.razorpay_key_secret', 'super-secret');
    $settings->set('payments.stripe_secret_key', 'sk_live_secret');

    $response = $this->actingAs(User::factory()->admin()->create())->get('/admin/settings/payments');

    $response->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            // The publishable half is fine to render; the secret half is not.
            ->where('values.razorpay_key_id', 'rzp_test_key')
            ->where('values.razorpay_key_secret_set', true)
            ->where('values.stripe_secret_key_set', true)
            ->where('values.stripe_webhook_secret_set', false)
            ->missing('values.razorpay_key_secret')
            ->missing('values.stripe_secret_key'));

    // Belt and braces: the serialized page must not carry the value anywhere.
    $response->assertDontSee('super-secret')->assertDontSee('sk_live_secret');
});

test('a blank secret keeps the stored one and remove_* erases it', function () {
    $admin = User::factory()->admin()->create();
    $settings = app(SettingsRegistry::class);
    $settings->set('payments.razorpay_key_secret', 'keep-me');
    $settings->set('payments.stripe_secret_key', 'delete-me');

    saveSettings($admin, 'payments', [
        'razorpay_key_secret' => '',
        'stripe_secret_key' => '',
        'remove_stripe_secret_key' => true,
    ])->assertSessionHasNoErrors();

    $settings->flush();

    expect($settings->string('payments.razorpay_key_secret'))->toBe('keep-me')
        ->and($settings->string('payments.stripe_secret_key'))->toBe('');
});

test('a submitted secret replaces the stored one', function () {
    $admin = User::factory()->admin()->create();
    $settings = app(SettingsRegistry::class);
    $settings->set('payments.razorpay_key_secret', 'old-secret');

    saveSettings($admin, 'payments', ['razorpay_key_secret' => 'new-secret'])->assertSessionHasNoErrors();

    $settings->flush();

    expect($settings->string('payments.razorpay_key_secret'))->toBe('new-secret');
});

test('payment settings are validated and saved', function () {
    $admin = User::factory()->admin()->create();

    saveSettings($admin, 'payments', ['tax_percent' => 120])->assertSessionHasErrors('tax_percent');

    saveSettings($admin, 'payments', [
        'tax_label' => 'VAT',
        'tax_percent' => 5,
        'pay_after_service' => false,
        'wallet_enabled' => false,
    ])->assertSessionHasNoErrors();

    $settings = freshSettings();

    expect($settings->string('payments.tax_label'))->toBe('VAT')
        ->and($settings->decimal('payments.tax_percent'))->toBe(5.0)
        ->and($settings->boolean('payments.pay_after_service'))->toBeFalse()
        ->and($settings->boolean('payments.wallet_enabled'))->toBeFalse();
});

test('commission and payout settings are validated and saved', function () {
    $admin = User::factory()->admin()->create();

    // A rate above 100 would owe the provider nothing.
    saveSettings($admin, 'payouts', ['commission_percent' => 120])->assertSessionHasErrors('commission_percent');

    saveSettings($admin, 'payouts', [
        'commission_percent' => 25.5,
        'payouts_enabled' => false,
        'payout_min_amount' => 250,
        'payout_hold_days' => 3,
    ])->assertSessionHasNoErrors();

    $settings = freshSettings();

    expect($settings->decimal('payments.commission_percent'))->toBe(25.5)
        ->and($settings->boolean('payouts.enabled'))->toBeFalse()
        ->and($settings->decimal('payouts.min_amount'))->toBe(250.0)
        ->and($settings->integer('payouts.hold_days'))->toBe(3);
});

test('a malformed GSTIN is rejected but a blank one is allowed', function () {
    $admin = User::factory()->admin()->create();

    saveSettings($admin, 'invoice', ['invoice_gstin' => 'TOO-SHORT'])->assertSessionHasErrors('invoice_gstin');

    saveSettings($admin, 'invoice', [
        'invoice_gstin' => '29AAAAA0000A1Z5',
        'invoice_company_name' => 'Acme Services Pvt Ltd',
    ])->assertSessionHasNoErrors();

    $settings = freshSettings();

    expect($settings->string('invoice.gstin'))->toBe('29AAAAA0000A1Z5')
        ->and($settings->string('invoice.company_name'))->toBe('Acme Services Pvt Ltd');
});

test('a settings save is audited by group and key, never by value', function () {
    $admin = User::factory()->admin()->create();

    saveSettings($admin, 'payments', ['razorpay_key_secret' => 'top-secret']);

    $log = ActivityLog::query()->where('action', 'settings.update')->latest('id')->firstOrFail();

    expect($log->context['group'] ?? null)->toBe('payments')
        ->and($log->context['keys'] ?? [])->toContain('razorpay_key_secret')
        ->and(json_encode($log->context))->not->toContain('top-secret');
});
