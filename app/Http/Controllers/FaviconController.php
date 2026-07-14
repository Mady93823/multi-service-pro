<?php

namespace App\Http\Controllers;

use App\Domain\Settings\SettingsRegistry;
use App\Support\BrandMark;
use Illuminate\Http\Response;

/**
 * The generated favicon (`/favicon.svg`).
 *
 * The starter kit shipped a **zero-byte** `public/favicon.ico` and no `<link
 * rel="icon">` at all, so every install browsed with a blank tab icon. It could
 * not simply be filled in either: a static file would put our mark on the
 * buyer's site, which the white-label rule (D8) forbids.
 *
 * So the default icon is derived from what the buyer has already told us —
 * their app name and their brand colour. An install that uploads a favicon in
 * **Settings → Branding** never reaches this route.
 */
class FaviconController extends Controller
{
    public function __invoke(SettingsRegistry $settings): Response
    {
        $svg = BrandMark::faviconSvg(
            $settings->string('branding.app_name', (string) config('app.name')),
            $settings->string('branding.primary_color') ?: BrandMark::DEFAULT_COLOR,
        );

        // Cached, but revalidated: an admin who changes the brand colour should
        // see the tab follow within the hour, not next year.
        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
