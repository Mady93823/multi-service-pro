<?php

namespace App\Support;

/**
 * Every upload allowlist in one place.
 *
 * Ten form requests had each grown their own copy of `mimes:jpg,jpeg,png,webp`.
 * They agreed today, which is exactly why nobody would have noticed the day one
 * of them drifted — and the one that drifts is the one that admits the file the
 * rest refuse. `UploadRuleSweepTest` fails any form request that spells a mime
 * list out by hand.
 *
 * Raster only, never SVG: an SVG is a script container, and every collection
 * here is rendered back into a page somewhere.
 */
final class UploadRules
{
    /** Kilobytes. A phone photo clears 4 MB comfortably; a 40 MB one is a mistake or an attack. */
    public const MAX_KB = 4096;

    /** @var list<string> */
    public const IMAGE_MIMES = ['jpg', 'jpeg', 'png', 'webp'];

    /** @var list<string> */
    public const DOCUMENT_MIMES = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    /**
     * A picture that will be displayed inline (banners, review photos, blocks).
     *
     * @return list<string>
     */
    public static function image(): array
    {
        return ['image', 'mimes:'.implode(',', self::IMAGE_MIMES), 'max:'.self::MAX_KB];
    }

    /**
     * Proof of something — KYC, a bank receipt, a ticket attachment. A PDF is
     * allowed, which is why these are served as downloads and never inline
     * (see `ServesPrivateFiles`).
     *
     * @return list<string>
     */
    public static function document(): array
    {
        return ['file', 'mimes:'.implode(',', self::DOCUMENT_MIMES), 'max:'.self::MAX_KB];
    }
}
