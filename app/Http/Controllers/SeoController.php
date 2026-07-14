<?php

namespace App\Http\Controllers;

use App\Domain\Seo\SitemapBuilder;
use App\Domain\Settings\SettingsRegistry;
use Illuminate\Http\Response;

/**
 * `sitemap.xml` and `robots.txt` (M24).
 *
 * Both are generated, never static files: a white-label install's URLs depend
 * on its own content, and `public/robots.txt` was deleted so this route is what
 * a crawler actually reaches.
 */
class SeoController extends Controller
{
    public function sitemap(SitemapBuilder $sitemap): Response
    {
        // A switched-off sitemap 404s rather than serving an empty <urlset>: an
        // empty sitemap tells a crawler the site has no pages.
        abort_unless($sitemap->enabled(), 404);

        return response()
            ->view('seo.sitemap', ['urls' => $sitemap->urls()])
            ->header('Content-Type', 'application/xml');
    }

    public function robots(SettingsRegistry $settings, SitemapBuilder $sitemap): Response
    {
        $lines = [
            'User-agent: *',
            // The panels are not content. Keeping them out of an index is not
            // security — the middleware is — but it keeps crawlers off login walls.
            'Disallow: /admin',
            'Disallow: /provider',
            'Disallow: /settings',
            'Disallow: /install',
            'Disallow: /checkout',
        ];

        if ($sitemap->enabled()) {
            $lines[] = '';
            $lines[] = 'Sitemap: '.url('/sitemap.xml');
        }

        $extra = trim($settings->string('seo.robots_extra'));

        if ($extra !== '') {
            $lines[] = '';
            $lines[] = $extra;
        }

        return response(implode("\n", $lines)."\n", 200)
            ->header('Content-Type', 'text/plain');
    }
}
