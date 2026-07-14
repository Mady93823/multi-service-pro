<?php

namespace App\Support;

/**
 * The two things every screen needs to know about the buyer's brand colour:
 * what text can be read on top of it, and what the site's icon looks like when
 * nobody has uploaded one.
 *
 * The luminance rule is deliberately duplicated in `resources/js/components/
 * brand-theme.tsx` — same reason `Money::format()` is duplicated in
 * `lib/format.ts` (D23): the server paints the first frame (no flash of the
 * wrong colour) and the browser repaints when the setting changes without a
 * reload. Two callers, one rule; if it ever changes, it changes in both.
 */
class BrandMark
{
    /** The theme's own default, matching `--primary` in `app.css`. */
    public const DEFAULT_COLOR = '#4f46e5';

    public static function isValidColor(string $color): bool
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1;
    }

    /**
     * Black or white — whichever the eye can actually read on this colour.
     *
     * A buyer who picks a pale yellow brand colour must not end up with white
     * text on it: that is an unreadable button, shipped by us, on their site.
     * The threshold is WCAG relative luminance, not a guess at "is it light".
     */
    public static function foregroundFor(string $color): string
    {
        if (! self::isValidColor($color)) {
            return '#ffffff';
        }

        $channel = static function (int $value): float {
            $c = $value / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        $luminance = 0.2126 * $channel((int) hexdec(substr($color, 1, 2)))
            + 0.7152 * $channel((int) hexdec(substr($color, 3, 2)))
            + 0.0722 * $channel((int) hexdec(substr($color, 5, 2)));

        return $luminance > 0.45 ? '#14141a' : '#ffffff';
    }

    /**
     * The fallback favicon: the app's own initial on the brand colour.
     *
     * Drawn rather than shipped, because a shipped icon would be *our* mark on
     * *their* site — the white-label rule (D8) reaches the browser tab. An
     * install that has uploaded a favicon never sees this.
     */
    public static function faviconSvg(string $appName, string $color): string
    {
        $color = self::isValidColor($color) ? $color : self::DEFAULT_COLOR;
        $text = self::foregroundFor($color);

        $initial = mb_strtoupper(mb_substr(trim($appName), 0, 1)) ?: 'U';
        $initial = htmlspecialchars($initial, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" role="img" aria-label="{$initial}">
                <rect width="64" height="64" rx="14" fill="{$color}"/>
                <text x="32" y="33" fill="{$text}" font-family="system-ui, -apple-system, 'Segoe UI', sans-serif"
                      font-size="34" font-weight="700" text-anchor="middle" dominant-baseline="central">{$initial}</text>
            </svg>
            SVG;
    }
}
