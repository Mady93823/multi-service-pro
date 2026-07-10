<?php

/**
 * Reconcile lang/en.json against the keys actually used in code (D8/D9).
 *
 * Mirrors the extraction rules of tests/Feature/Localization/
 * TranslationCatalogTest.php exactly — keep the two in step: adds missing keys
 * (value = key), drops orphans, keeps existing translations, sorts the catalog.
 *
 * Usage: php scratchpad/reconcile_catalog.php [--dry-run]
 */
$root = dirname(__DIR__);
$dryRun = in_array('--dry-run', $argv, true);

/** @return list<string> */
$collect = static function () use ($root): array {
    $keys = [];

    $frontend = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/resources/js'));

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

    // Blade counts: the invoice PDF (M09) is the one user-facing surface
    // React never renders.
    foreach ([$root.'/app', $root.'/resources/views'] as $dir) {
        $backend = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

        foreach ($backend as $file) {
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

    return array_values(array_unique($keys));
};

$path = $root.'/lang/en.json';
/** @var array<string, string> $catalog */
$catalog = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

$used = $collect();
$missing = array_values(array_diff($used, array_keys($catalog)));
$orphans = array_values(array_diff(array_keys($catalog), $used));

$next = [];
foreach ($used as $key) {
    $next[$key] = $catalog[$key] ?? $key;
}

ksort($next, SORT_NATURAL | SORT_FLAG_CASE);

printf("used: %d | added: %d | removed: %d\n", count($used), count($missing), count($orphans));

foreach ($missing as $key) {
    echo "  + {$key}\n";
}
foreach ($orphans as $key) {
    echo "  - {$key}\n";
}

if ($dryRun) {
    echo "dry run — lang/en.json untouched\n";

    exit(0);
}

file_put_contents(
    $path,
    json_encode($next, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n",
);

echo "wrote lang/en.json\n";
