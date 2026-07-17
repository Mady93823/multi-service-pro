<?php

namespace App\Domain\Installer;

/**
 * The installed marker: a single `INSTALL=false` line in .env.
 *
 * A fresh copy of the product is `.env.example` -> `.env`, and the example
 * carries the line — so unpacking the zip *is* opening the installer, with no
 * command to run first. The last wizard step deletes the line, and the app is
 * an application from the next request onward.
 *
 * It used to be storage/app/installed.lock, which meant absence = not
 * installed: a wiped storage directory silently reopened the wizard on a live
 * site. Absence now means installed, and the only thing that opens the wizard
 * is somebody putting the line back deliberately.
 */
class InstallLock
{
    public const ENV_KEY = 'INSTALL';

    public static function installed(): bool
    {
        return (bool) config('app.installed');
    }

    /**
     * Finish the install: drop the line, then forget the cached config that
     * still remembers it. Without the clear, a buyer who ran `config:cache`
     * before installing would be left with a wizard that never closes.
     */
    public static function write(?EnvWriter $env = null): void
    {
        ($env ?? EnvWriter::forApp())->remove([self::ENV_KEY]);

        config(['app.installed' => true]);
    }
}
