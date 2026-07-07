<?php

use App\Domain\Localization\TranslationLoader;
use App\Domain\Settings\Enums\SettingType;
use App\Domain\Settings\SettingsRegistry;

test('the configured locale is applied to the application', function () {
    app(SettingsRegistry::class)->set('localization.locale', 'hi', SettingType::String, 'localization');

    $this->get(route('catalog.index'))->assertOk();

    expect(app()->getLocale())->toBe('hi');
});

test('an invalid locale setting is ignored', function () {
    app(SettingsRegistry::class)->set('localization.locale', '../../evil', SettingType::String, 'localization');

    $this->get(route('catalog.index'))->assertOk();

    expect(app()->getLocale())->toBe('en');
});

test('translations for the active locale are shared with Inertia', function () {
    $path = lang_path('zz.json');
    file_put_contents($path, json_encode(['Services' => 'Zervices'], JSON_THROW_ON_ERROR));

    try {
        app(SettingsRegistry::class)->set('localization.locale', 'zz', SettingType::String, 'localization');

        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('translations.Services', 'Zervices'));
    } finally {
        unlink($path);
    }
});

test('the english catalog is shared as-is', function () {
    $this->get(route('catalog.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('translations.Services', 'Services'));
});

test('translation loader returns an empty catalog for missing or malformed files', function () {
    $loader = new TranslationLoader;

    expect($loader->forLocale('does-not-exist'))->toBe([]);

    $path = lang_path('bad.json');
    file_put_contents($path, '{not json');

    try {
        expect($loader->forLocale('bad'))->toBe([]);
    } finally {
        unlink($path);
    }
});
