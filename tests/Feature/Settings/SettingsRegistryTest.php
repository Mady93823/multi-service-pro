<?php

use App\Domain\Settings\Enums\SettingType;
use App\Domain\Settings\SettingsRegistry;
use Database\Seeders\SettingsSeeder;

beforeEach(function () {
    $this->registry = app(SettingsRegistry::class);
    $this->registry->flush();
});

test('seeded defaults are readable', function () {
    expect($this->registry->string('branding.app_name'))->toBe(config('app.name'))
        ->and($this->registry->string('localization.currency'))->toBe('INR')
        ->and($this->registry->string('localization.timezone'))->toBe('Asia/Kolkata')
        ->and($this->registry->boolean('features.otp_required'))->toBeFalse();
});

test('unknown key falls back to the given default', function () {
    expect($this->registry->get('nope.missing', 'fallback'))->toBe('fallback')
        ->and($this->registry->string('nope.missing', 'x'))->toBe('x')
        ->and($this->registry->integer('nope.missing', 7))->toBe(7);
});

test('set persists a typed value and busts the cache', function () {
    $this->registry->set('features.otp_required', true);

    $this->assertDatabaseHas('settings', [
        'key' => 'features.otp_required',
        'value' => '1',
        'type' => 'bool',
        'group' => 'features',
    ]);

    // Fresh instance — must read through cache/DB, not stale memory.
    expect(app()->make(SettingsRegistry::class)->boolean('features.otp_required'))->toBeTrue();
});

test('set accepts new keys with explicit type and group', function () {
    $this->registry->set('booking.max_addons', 5, SettingType::Integer, 'booking');

    expect($this->registry->integer('booking.max_addons'))->toBe(5);
    $this->assertDatabaseHas('settings', ['key' => 'booking.max_addons', 'group' => 'booking', 'type' => 'int']);
});

test('group returns only its own keys', function () {
    $keys = array_keys($this->registry->group('branding'));

    expect($keys)->toContain('branding.app_name', 'branding.logo_path', 'branding.primary_color')
        ->and($keys)->not->toContain('localization.currency');
});

test('reseeding keeps admin-changed values', function () {
    $this->registry->set('branding.app_name', 'Client Brand');

    $this->seed(SettingsSeeder::class);

    expect(app(SettingsRegistry::class)->string('branding.app_name'))->toBe('Client Brand');
});

test('json settings round-trip', function () {
    $this->registry->set('branding.colors', ['primary' => '#ff0000'], SettingType::Json, 'branding');

    expect($this->registry->get('branding.colors'))->toBe(['primary' => '#ff0000']);
});
