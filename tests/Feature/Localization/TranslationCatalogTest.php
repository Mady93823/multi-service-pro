<?php

/**
 * Guard test for the white-label rule (D8/D9): every literal translation key
 * used in the frontend (t('...')) and every natural-language server string
 * (__('...')) must exist in lang/en.json, so translators and the future
 * language manager always see the full catalog.
 *
 * Blade is scanned too — the invoice PDF (M09) is the one user-facing surface
 * React never renders. Keep these regexes in step with
 * scratchpad/reconcile_catalog.php.
 */
function collectTranslationKeys(): array
{
    $keys = [];

    $frontend = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('js')));

    foreach ($frontend as $file) {
        if ($file->isDir() || ! in_array($file->getExtension(), ['tsx', 'ts'], true)) {
            continue;
        }
        $contents = (string) file_get_contents($file->getPathname());
        preg_match_all("/\\bt\\(\\s*'((?:[^'\\\\]|\\\\.)+)'/", $contents, $single);
        preg_match_all('/\bt\(\s*"((?:[^"\\\\]|\\\\.)+)"/', $contents, $double);

        foreach ($single[1] as $key) {
            $keys[] = str_replace("\\'", "'", $key);
        }
        foreach ($double[1] as $key) {
            $keys[] = str_replace('\\"', '"', $key);
        }
    }

    foreach ([app_path(), resource_path('views')] as $root) {
        $server = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($server as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }
            $contents = (string) file_get_contents($file->getPathname());
            preg_match_all("/__\\(\\s*'((?:[^'\\\\]|\\\\.)+)'/", $contents, $matches);

            foreach ($matches[1] as $key) {
                $key = str_replace("\\'", "'", $key);
                if (str_contains($key, ' ')) { // natural string, not a lang-file dot key
                    $keys[] = $key;
                }
            }
        }
    }

    return array_unique($keys);
}

test('every translation key used in code exists in the english catalog', function () {
    $catalog = json_decode((string) file_get_contents(lang_path('en.json')), true, 512, JSON_THROW_ON_ERROR);

    $missing = array_values(array_diff(collectTranslationKeys(), array_keys($catalog)));

    expect($missing)->toBe([], 'Missing from lang/en.json: '.implode(' | ', $missing));
});

test('the english catalog has no orphaned keys', function () {
    $catalog = json_decode((string) file_get_contents(lang_path('en.json')), true, 512, JSON_THROW_ON_ERROR);

    $orphans = array_values(array_diff(array_keys($catalog), collectTranslationKeys()));

    expect($orphans)->toBe([], 'In lang/en.json but unused in code: '.implode(' | ', $orphans));
});
