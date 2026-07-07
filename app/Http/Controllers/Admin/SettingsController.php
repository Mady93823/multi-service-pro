<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Settings\Actions\UpdateSettings;
use App\Domain\Settings\SettingsRegistry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(SettingsRegistry $settings): Response
    {
        $logoPath = $settings->string('branding.logo_path');

        return Inertia::render('admin/settings/edit', [
            'values' => [
                'app_name' => $settings->string('branding.app_name', (string) config('app.name')),
                'primary_color' => $settings->string('branding.primary_color') ?: null,
                'currency' => $settings->string('localization.currency', 'INR'),
                'timezone' => $settings->string('localization.timezone', 'Asia/Kolkata'),
                'locale' => $settings->string('localization.locale', 'en'),
                'logo_url' => $logoPath !== '' ? Storage::disk('public')->url($logoPath) : null,
            ],
        ]);
    }

    public function update(UpdateSettingsRequest $request, UpdateSettings $action): RedirectResponse
    {
        /** @var array{app_name: string, primary_color?: string|null, currency: string, timezone: string, locale: string, remove_logo?: bool} $data */
        $data = $request->safe()->except(['logo']);

        $logo = $request->file('logo');
        $action->handle($data, is_array($logo) ? null : $logo);

        return back()->with('success', __('Settings saved.'));
    }
}
