<?php

namespace App\Domain\Installer;

/**
 * Pre-install environment checks for the web installer (M15).
 */
class RequirementsChecker
{
    private const MIN_PHP = '8.2.0';

    private const EXTENSIONS = [
        'pdo_mysql',
        'mbstring',
        'openssl',
        'curl',
        'gd',
        'zip',
        'fileinfo',
        'bcmath',
        'ctype',
        'xml',
        'dom',
    ];

    /**
     * @return array{passed: bool, checks: list<array{label: string, passed: bool, detail: string}>}
     */
    public function run(): array
    {
        $checks = [];

        $checks[] = [
            'label' => __('PHP >= :version', ['version' => self::MIN_PHP]),
            'passed' => version_compare(PHP_VERSION, self::MIN_PHP, '>='),
            'detail' => __('Running :version', ['version' => PHP_VERSION]),
        ];

        foreach (self::EXTENSIONS as $extension) {
            $checks[] = [
                'label' => __('PHP extension: :name', ['name' => $extension]),
                'passed' => extension_loaded($extension),
                'detail' => extension_loaded($extension) ? __('Loaded') : __('Missing'),
            ];
        }

        foreach ($this->writablePaths() as $label => $path) {
            $writable = $this->isWritable($path);
            $checks[] = [
                'label' => __('Writable: :path', ['path' => $label]),
                'passed' => $writable,
                'detail' => $writable ? __('Writable') : __('Not writable'),
            ];
        }

        return [
            'passed' => ! in_array(false, array_column($checks, 'passed'), true),
            'checks' => $checks,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function writablePaths(): array
    {
        return [
            'storage/' => storage_path(),
            'bootstrap/cache/' => base_path('bootstrap/cache'),
            '.env' => base_path('.env'),
        ];
    }

    private function isWritable(string $path): bool
    {
        if (file_exists($path)) {
            return is_writable($path);
        }

        // .env may not exist yet on a fresh upload — its directory must be writable.
        return is_writable(dirname($path));
    }
}
