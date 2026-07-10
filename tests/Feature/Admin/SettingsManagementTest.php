<?php

use App\Domain\Settings\SettingsRegistry;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    app(SettingsRegistry::class)->flush();
});

function validSettingsPayload(array $overrides = []): array
{
    return array_merge([
        'app_name' => 'Acme Services',
        'primary_color' => '#4f46e5',
        'currency' => 'INR',
        'timezone' => 'Asia/Kolkata',
        'locale' => 'en',
        'booking_code_prefix' => 'BK',
        'slot_minutes' => 60,
        'day_starts' => '08:00',
        'day_ends' => '20:00',
        'lead_time_hours' => 2,
        'max_days_ahead' => 7,
        'job_otp_required' => true,
        'free_cancel_hours' => 2,
        'cancellation_fee_type' => 'percent',
        'cancellation_fee_value' => 10,
        'reschedule_min_hours' => 2,
        'tax_label' => 'GST',
        'tax_percent' => 18,
        'payment_timeout_minutes' => 30,
        'pay_after_service' => true,
        'wallet_enabled' => true,
        'commission_percent' => 20,
        'payouts_enabled' => true,
        'payout_min_amount' => 500,
        'payout_hold_days' => 7,
        'invoice_prefix' => 'INV',
    ], $overrides);
}

test('guests and non-admins cannot open settings', function () {
    $this->get('/admin/settings')->assertRedirect('/login');

    $this->actingAs(User::factory()->customer()->create())
        ->get('/admin/settings')
        ->assertForbidden();

    $this->actingAs(User::factory()->provider()->create())
        ->put('/admin/settings', validSettingsPayload())
        ->assertForbidden();
});

test('admin sees the settings form with current values', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/settings')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/settings/edit')
            ->where('values.currency', 'INR')
            ->where('values.timezone', 'Asia/Kolkata'));
});

test('admin can update branding and localization settings', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put('/admin/settings', validSettingsPayload([
            'app_name' => 'Client Brand',
            'currency' => 'USD',
            'timezone' => 'America/New_York',
        ]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $settings = app()->make(SettingsRegistry::class);
    $settings->flush();

    expect($settings->string('branding.app_name'))->toBe('Client Brand')
        ->and($settings->string('branding.primary_color'))->toBe('#4f46e5')
        ->and($settings->string('localization.currency'))->toBe('USD')
        ->and($settings->string('localization.timezone'))->toBe('America/New_York');
});

test('updated app name is shared with every Inertia page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put('/admin/settings', validSettingsPayload(['app_name' => 'Client Brand']));

    $this->actingAs($admin)
        ->get('/admin/dashboard')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('name', 'Client Brand'));
});

test('invalid color, currency and timezone are rejected', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put('/admin/settings', validSettingsPayload([
            'primary_color' => 'red',
            'currency' => 'rupees',
            'timezone' => 'Mars/Olympus',
        ]))
        ->assertSessionHasErrors(['primary_color', 'currency', 'timezone']);
});

test('booking settings are validated and saved', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put('/admin/settings', validSettingsPayload([
            'day_ends' => '07:00', // before day_starts
            'cancellation_fee_type' => 'percent',
            'cancellation_fee_value' => 150, // >100% not allowed for percent
        ]))
        ->assertSessionHasErrors(['day_ends', 'cancellation_fee_value']);

    $this->actingAs($admin)
        ->put('/admin/settings', validSettingsPayload([
            'slot_minutes' => 120,
            'job_otp_required' => false,
            'tax_label' => 'VAT',
            'tax_percent' => 5,
        ]))
        ->assertSessionHasNoErrors();

    $settings = app()->make(SettingsRegistry::class);
    $settings->flush();

    expect($settings->integer('booking.slot_minutes'))->toBe(120)
        ->and($settings->boolean('booking.job_otp_required'))->toBeFalse()
        ->and($settings->string('payments.tax_label'))->toBe('VAT')
        ->and($settings->decimal('payments.tax_percent'))->toBe(5.0);
});

test('logo can be uploaded and removed', function () {
    Storage::fake('public');
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put('/admin/settings', validSettingsPayload([
        'logo' => UploadedFile::fake()->image('logo.png', 200, 60),
    ]));

    $settings = app()->make(SettingsRegistry::class);
    $settings->flush();
    $path = $settings->string('branding.logo_path');

    expect($path)->not->toBe('');
    Storage::disk('public')->assertExists($path);

    $this->actingAs($admin)->put('/admin/settings', validSettingsPayload(['remove_logo' => true]));

    $settings->flush();
    expect($settings->string('branding.logo_path'))->toBe('');
    Storage::disk('public')->assertMissing($path);
});

test('gateway secrets are never sent to the browser', function () {
    $settings = app(SettingsRegistry::class);
    $settings->set('payments.razorpay_key_id', 'rzp_test_key');
    $settings->set('payments.razorpay_key_secret', 'super-secret');
    $settings->set('payments.stripe_secret_key', 'sk_live_secret');

    $response = $this->actingAs(User::factory()->admin()->create())->get('/admin/settings');

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

    $this->actingAs($admin)->put('/admin/settings', validSettingsPayload([
        'razorpay_key_secret' => '',
        'stripe_secret_key' => '',
        'remove_stripe_secret_key' => true,
    ]))->assertSessionHasNoErrors();

    $settings->flush();

    expect($settings->string('payments.razorpay_key_secret'))->toBe('keep-me')
        ->and($settings->string('payments.stripe_secret_key'))->toBe('');
});

test('a submitted secret replaces the stored one', function () {
    $admin = User::factory()->admin()->create();
    $settings = app(SettingsRegistry::class);
    $settings->set('payments.razorpay_key_secret', 'old-secret');

    $this->actingAs($admin)->put('/admin/settings', validSettingsPayload([
        'razorpay_key_secret' => 'new-secret',
    ]))->assertSessionHasNoErrors();

    $settings->flush();

    expect($settings->string('payments.razorpay_key_secret'))->toBe('new-secret');
});

test('payment settings are validated and saved', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put('/admin/settings', validSettingsPayload([
            'payment_timeout_minutes' => 1, // below the 5-minute floor
        ]))
        ->assertSessionHasErrors('payment_timeout_minutes');

    $this->actingAs(User::factory()->admin()->create())
        ->put('/admin/settings', validSettingsPayload([
            'payment_timeout_minutes' => 45,
            'pay_after_service' => false,
            'wallet_enabled' => false,
        ]))
        ->assertSessionHasNoErrors();

    $settings = app()->make(SettingsRegistry::class);
    $settings->flush();

    expect($settings->integer('booking.payment_timeout_minutes'))->toBe(45)
        ->and($settings->boolean('payments.pay_after_service'))->toBeFalse()
        ->and($settings->boolean('payments.wallet_enabled'))->toBeFalse();
});

test('commission and payout settings are validated and saved', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put('/admin/settings', validSettingsPayload([
            'commission_percent' => 120, // a rate above 100 would owe the provider nothing
        ]))
        ->assertSessionHasErrors('commission_percent');

    $this->actingAs(User::factory()->admin()->create())
        ->put('/admin/settings', validSettingsPayload([
            'commission_percent' => 25.5,
            'payouts_enabled' => false,
            'payout_min_amount' => 250,
            'payout_hold_days' => 3,
        ]))
        ->assertSessionHasNoErrors();

    $settings = app()->make(SettingsRegistry::class);
    $settings->flush();

    expect($settings->decimal('payments.commission_percent'))->toBe(25.5)
        ->and($settings->boolean('payouts.enabled'))->toBeFalse()
        ->and($settings->decimal('payouts.min_amount'))->toBe(250.0)
        ->and($settings->integer('payouts.hold_days'))->toBe(3);
});

test('a malformed GSTIN is rejected but a blank one is allowed', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put('/admin/settings', validSettingsPayload(['invoice_gstin' => 'TOO-SHORT']))
        ->assertSessionHasErrors('invoice_gstin');

    $this->actingAs(User::factory()->admin()->create())
        ->put('/admin/settings', validSettingsPayload([
            'invoice_gstin' => '29AAAAA0000A1Z5',
            'invoice_company_name' => 'Acme Services Pvt Ltd',
        ]))
        ->assertSessionHasNoErrors();

    $settings = app()->make(SettingsRegistry::class);
    $settings->flush();

    expect($settings->string('invoice.gstin'))->toBe('29AAAAA0000A1Z5')
        ->and($settings->string('invoice.company_name'))->toBe('Acme Services Pvt Ltd');
});
