<?php

namespace App\Domain\Installer;

use Illuminate\Support\Facades\Storage;

/**
 * The installed marker. Its absence routes every request to the web
 * installer; writing it (last wizard step) opens the application.
 */
class InstallLock
{
    private const FILE = 'installed.lock';

    public static function installed(): bool
    {
        // Escape hatch for tests and pre-provisioned environments.
        // (phpunit delivers booleans as '1', so validate loosely.)
        if (filter_var(config('app.installed'), FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        return Storage::disk('local')->exists(self::FILE);
    }

    public static function write(): void
    {
        Storage::disk('local')->put(self::FILE, (string) json_encode([
            'installed_at' => now()->toIso8601String(),
            'version' => '1.0.0',
        ], JSON_PRETTY_PRINT));
    }
}
