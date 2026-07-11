<?php

namespace App\Domain\Cms;

use Illuminate\Support\Str;

/**
 * The single markdown → HTML path for CMS content (ADR D20).
 *
 * Admin-authored bodies are still untrusted (CodeCanyon installs hand the
 * admin panel to unknown operators), so raw HTML in the source is stripped
 * outright and unsafe link/image schemes (javascript:, data:) are dropped.
 * Nothing the markdown grammar itself cannot express survives to output —
 * a whitelist by construction, no purifier dependency needed.
 */
class MarkdownRenderer
{
    public function render(string $markdown): string
    {
        return (string) Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]);
    }
}
