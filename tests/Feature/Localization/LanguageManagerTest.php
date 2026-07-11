<?php

use App\Domain\Localization\TranslationLoader;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Language;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

function langAdmin(): User
{
    return User::factory()->admin()->create();
}

// Locale codes reserved for this file. Files land in the real lang/
// directory, so tests only use these codes and afterEach removes them.
afterEach(function () {
    foreach (['xa', 'xb', 'xc', 'xd'] as $code) {
        @unlink(lang_path($code.'.json'));
    }
});

it('lists languages with the default flagged and catalog progress', function () {
    $this->actingAs(langAdmin())
        ->get(route('admin.languages.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia
            ->component('admin/languages/index')
            ->where('catalog_size', fn ($size): bool => (int) $size > 100)
            ->where('languages', function ($languages): bool {
                $en = collect($languages)->firstWhere('code', 'en');

                return $en !== null && $en['is_default'] === true;
            }));
});

it('adds a language, normalizing the code', function () {
    $this->actingAs(langAdmin())->post(route('admin.languages.store'), [
        'code' => '  XA ',
        'name' => 'Test Language',
        'native_name' => null,
        'is_active' => true,
    ])->assertRedirect(route('admin.languages.index'));

    expect(Language::query()->where('code', 'xa')->exists())->toBeTrue();
});

it('rejects malformed or traversal locale codes', function (string $code) {
    $this->actingAs(langAdmin())
        ->from(route('admin.languages.index'))
        ->post(route('admin.languages.store'), [
            'code' => $code,
            'name' => 'Evil',
        ])->assertSessionHasErrors('code');

    expect(Language::query()->where('name', 'Evil')->exists())->toBeFalse();
})->with(['../evil', '..', 'e', 'english-language', 'xx.php', 'xx/..']);

it('rejects a duplicate code', function () {
    $this->actingAs(langAdmin())
        ->post(route('admin.languages.store'), ['code' => 'en', 'name' => 'English Again'])
        ->assertSessionHasErrors('code');
});

it('updates name and active flag but never the code', function () {
    $language = Language::factory()->create(['code' => 'xb', 'name' => 'Before']);

    $this->actingAs(langAdmin())->put(route('admin.languages.update', $language), [
        'name' => 'After',
        'native_name' => 'Native',
        'is_active' => false,
        'code' => 'hacked',
    ])->assertRedirect(route('admin.languages.index'));

    expect($language->refresh())
        ->name->toBe('After')
        ->is_active->toBeFalse()
        ->code->toBe('xb');
});

it('deletes a language along with its translation file', function () {
    $language = Language::factory()->create(['code' => 'xc']);
    file_put_contents(lang_path('xc.json'), '{"Dashboard":"Zq"}');

    $this->actingAs(langAdmin())
        ->delete(route('admin.languages.destroy', $language))
        ->assertRedirect(route('admin.languages.index'));

    expect(Language::query()->whereKey($language->id)->exists())->toBeFalse()
        ->and(is_file(lang_path('xc.json')))->toBeFalse();
});

it('refuses to delete the default language', function () {
    $en = Language::query()->where('code', 'en')->firstOrFail();

    $this->actingAs(langAdmin())
        ->from(route('admin.languages.index'))
        ->delete(route('admin.languages.destroy', $en))
        ->assertSessionHasErrors('language');

    expect(Language::query()->where('code', 'en')->exists())->toBeTrue();
});

it('refuses to delete the current site locale', function () {
    $language = Language::factory()->create(['code' => 'xd']);
    app(SettingsRegistry::class)->set('localization.locale', 'xd');

    $this->actingAs(langAdmin())
        ->from(route('admin.languages.index'))
        ->delete(route('admin.languages.destroy', $language))
        ->assertSessionHasErrors('language');

    expect(Language::query()->where('code', 'xd')->exists())->toBeTrue();
});

it('shows the translation editor with the english catalog as source', function () {
    $language = Language::factory()->create(['code' => 'xa']);

    $this->actingAs(langAdmin())
        ->get(route('admin.languages.translations.edit', $language))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $inertia) => $inertia
            ->component('admin/languages/translations')
            ->where('language.code', 'xa')
            ->where('entries', function ($entries): bool {
                $first = collect($entries)->first();

                return is_array($first)
                    && array_key_exists('key', $first)
                    && array_key_exists('source', $first)
                    && $first['value'] === '';
            }));
});

it('redirects away from editing the default catalog', function () {
    $en = Language::query()->where('code', 'en')->firstOrFail();

    $this->actingAs(langAdmin())
        ->get(route('admin.languages.translations.edit', $en))
        ->assertRedirect(route('admin.languages.index'));
});

it('saves translations, dropping blanks and unknown keys, without touching en.json', function () {
    $language = Language::factory()->create(['code' => 'xa']);
    $enBefore = (string) file_get_contents(lang_path('en.json'));

    $catalogKey = array_key_first(app(TranslationLoader::class)->forLocale('en'));

    $this->actingAs(langAdmin())->put(route('admin.languages.translations.update', $language), [
        'translations' => [
            $catalogKey => 'Übersetzt ✓',
            'Some blank string' => '   ',
            'Not-in-catalog key' => 'should vanish',
        ],
    ])->assertRedirect();

    $saved = app(TranslationLoader::class)->forLocale('xa');

    expect($saved)->toBe([$catalogKey => 'Übersetzt ✓'])
        ->and((string) file_get_contents(lang_path('en.json')))->toBe($enBefore);
});

it('refuses to write the default catalog through the save endpoint', function () {
    $en = Language::query()->where('code', 'en')->firstOrFail();

    $this->actingAs(langAdmin())
        ->from(route('admin.languages.index'))
        ->put(route('admin.languages.translations.update', $en), [
            'translations' => ['Dashboard' => 'Pwned'],
        ])->assertSessionHasErrors('language');
});

it('blocks non-admins from the language manager', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.languages.index'))
        ->assertForbidden();
});
