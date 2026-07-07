<?php

namespace App\Http\Middleware;

use App\Domain\Installer\InstallLock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Not installed -> every web request lands on the installer.
 * Installed -> the installer no longer exists (404).
 */
class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        $installed = InstallLock::installed();

        if ($request->routeIs('install.*')) {
            // finish stays reachable right after the lock is written;
            // every other wizard step disappears once installed.
            abort_if($installed && ! $request->routeIs('install.finish'), 404);

            return $next($request);
        }

        if (! $installed) {
            return redirect()->route('install.requirements');
        }

        return $next($request);
    }
}
