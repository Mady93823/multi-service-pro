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
