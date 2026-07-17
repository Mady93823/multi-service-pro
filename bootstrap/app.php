<?php

use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\EnsureProviderApproved;
use App\Http\Middleware\EnsureUserActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Global, not in the `web` group, and that distinction is load-bearing:
        // `auth` sits in Laravel's middleware *priority* list, so it is sorted
        // ahead of anything we append to a group. An unauthenticated request is
        // then redirected out before a group middleware ever runs, and the login
        // redirect — along with every 403, 404 and 500 the exception handler
        // renders — would ship with no security headers at all. A global
        // middleware wraps the router itself, so it sees every response (P7.1).
        $middleware->append(SecurityHeaders::class);

        $middleware->web(append: [
            EnsureInstalled::class,
            EnsureUserActive::class,
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Gateway webhooks authenticate by signature, not session (M08).
        // PayU's return leg is a cross-site POST carrying no session at all —
        // its reverse hash is the authentication (D39).
        $middleware->validateCsrfTokens(except: ['webhooks/*', 'payments/payu/return']);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'provider.approved' => EnsureProviderApproved::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
