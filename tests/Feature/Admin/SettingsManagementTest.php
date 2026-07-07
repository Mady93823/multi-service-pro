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
