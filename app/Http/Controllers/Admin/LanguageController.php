<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Localization\Actions\DeleteLanguage;
use App\Domain\Localization\Actions\SaveTranslations;
use App\Domain\Localization\TranslationLoader;
use App\Domain\Settings\SettingsRegistry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveTranslationsRequest;
use App\Http\Requests\Admin\StoreLanguageRequest;
use App\Http\Requests\Admin\UpdateLanguageRequest;
use App\Models\Language;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LanguageController extends Controller
{
    public function __construct(
        private TranslationLoader $loader,
        private SettingsRegistry $settings,
    ) {}

    public function index(): Response
    {
        $catalogSize = count($this->loader->forLocale(Language::DEFAULT_CODE));
        $siteLocale = $this->settings->string('localization.locale', 'en');

        $languages = Language::query()
            ->orderByDesc('is_active')
            ->orderBy('code')
            ->get()
            ->map(fn (Language $language): array => [
                'id' => $language->id,
                'code' => $language->code,
                'name' => $language->name,
                'native_name' => $language->native_name,
                'is_active' => $language->is_active,
                'is_default' => $language->isDefault(),
                'is_site_locale' => $language->code === $siteLocale,
                'translated_count' => $language->isDefault()
                    ? $catalogSize
                    : count($this->loader->forLocale($language->code)),
            ])
            ->all();

        return Inertia::render('admin/languages/index', [
            'languages' => $languages,
            'catalog_size' => $catalogSize,
        ]);
    }

    public function store(StoreLanguageRequest $request): RedirectResponse
    {
        Language::query()->create($request->validated());

        return to_route('admin.languages.index')->with('success', __('Language added.'));
    }

    public function update(UpdateLanguageRequest $request, Language $language): RedirectResponse
    {
        $language->update($request->validated());

        return to_route('admin.languages.index')->with('success', __('Language updated.'));
    }

    public function destroy(Language $language, DeleteLanguage $action): RedirectResponse
    {
        $action->handle($language);

        return to_route('admin.languages.index')->with('success', __('Language deleted.'));
    }

    public function editTranslations(Language $language): Response|RedirectResponse
    {
        if ($language->isDefault()) {
            return to_route('admin.languages.index')
                ->with('error', __('The default language catalog is generated from source code and cannot be edited here.'));
        }

        $catalog = $this->loader->forLocale(Language::DEFAULT_CODE);
        $current = $this->loader->forLocale($language->code);

        $entries = [];
        foreach ($catalog as $key => $source) {
            $entries[] = [
                'key' => $key,
                'source' => $source,
                'value' => $current[$key] ?? '',
            ];
        }

        return Inertia::render('admin/languages/translations', [
            'language' => [
                'id' => $language->id,
                'code' => $language->code,
                'name' => $language->name,
            ],
            'entries' => $entries,
        ]);
    }

    public function updateTranslations(
        SaveTranslationsRequest $request,
        Language $language,
        SaveTranslations $action,
    ): RedirectResponse {
        /** @var array<string, mixed> $translations */
        $translations = $request->validated('translations');

        $saved = $action->handle($language, $translations);

        return back()->with('success', __(':count strings translated.', ['count' => (string) $saved]));
    }
}
