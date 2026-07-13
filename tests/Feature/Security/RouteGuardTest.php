<?php

use Illuminate\Routing\Route as RouteInstance;
use Illuminate\Support\Facades\Route;

/**
 * A sweep, not a sample. Every feature test proves *its own* routes are guarded;
 * this proves the ones nobody remembered to test are guarded too — the failure
 * mode is a new module shipping one endpoint outside its middleware group, and
 * it is invisible until someone finds it.
 */

/** @return list<RouteInstance> */
function appRoutes(): array
{
    return array_values(array_filter(
        Route::getRoutes()->getRoutes(),
        fn (RouteInstance $route): bool => ! str_starts_with((string) $route->uri(), '_'),
    ));
}

test('every admin route is behind auth and the admin role', function () {
    $unguarded = [];

    foreach (appRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'admin/')) {
            continue;
        }

        $middleware = $route->gatherMiddleware();

        if (! in_array('auth', $middleware, true) || ! in_array('role:admin', $middleware, true)) {
            $unguarded[] = $route->uri();
        }
    }

    expect($unguarded)->toBe([], 'These admin routes are missing auth + role:admin.');
});

test('every provider panel route is behind auth, the provider role and approval', function () {
    // Onboarding, KYC upload and support must stay reachable *before* approval —
    // a provider stuck in review is the one who most needs them (M05/M16).
    $preApproval = ['provider/onboarding', 'provider/profile', 'provider/documents'];
    $missingRole = [];
    $missingApproval = [];

    foreach (appRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'provider/')) {
            continue;
        }

        $middleware = $route->gatherMiddleware();

        if (! in_array('auth', $middleware, true) || ! in_array('role:provider', $middleware, true)) {
            $missingRole[] = $route->uri();
        }

        if (! in_array($route->uri(), $preApproval, true) && ! in_array('provider.approved', $middleware, true)) {
            $missingApproval[] = $route->uri();
        }
    }

    expect($missingRole)->toBe([], 'These provider routes are missing auth + role:provider.')
        ->and($missingApproval)->toBe([], 'These provider routes skip the approval gate.');
});

test('every gateway webhook is CSRF-exempt, unauthenticated and throttled', function () {
    // A webhook authenticates by HMAC over the raw body (M08, D15). Session auth
    // would break it; no throttle would make it a free DoS surface.
    $webhooks = array_values(array_filter(
        appRoutes(),
        fn (RouteInstance $route): bool => str_starts_with($route->uri(), 'webhooks/'),
    ));

    expect($webhooks)->not->toBe([], 'The webhook routes vanished — M08 payments cannot confirm.');

    foreach ($webhooks as $route) {
        $middleware = $route->gatherMiddleware();

        expect($middleware)->not->toContain('auth', $route->uri().' must not require a session.');

        $throttled = array_filter($middleware, fn ($item): bool => is_string($item) && str_starts_with($item, 'throttle:'));

        expect($throttled)->not->toBe([], $route->uri().' is not rate limited.');
    }
});

test('private file routes are authenticated', function () {
    // Every private-disk serve route (KYC, booking photos, review photos, ticket
    // attachments, exports) policy-checks inside its controller; the middleware
    // is the outer fence. Review photos are the one deliberate exception — the
    // storefront shows them to guests, and ReviewPolicy@view takes a nullable
    // user (M10).
    $guestReachable = ['reviews/{review}/photos/{media}'];
    $unauthenticated = [];

    foreach (appRoutes() as $route) {
        $uri = $route->uri();

        $isFileRoute = str_contains($uri, '/photos/')
            || str_contains($uri, '/attachments/')
            || str_contains($uri, '/proof/')
            || str_starts_with($uri, 'provider-documents/')
            || str_starts_with($uri, 'admin/exports/');

        if (! $isFileRoute || in_array($uri, $guestReachable, true)) {
            continue;
        }

        if (! in_array('auth', $route->gatherMiddleware(), true)) {
            $unauthenticated[] = $uri;
        }
    }

    expect($unauthenticated)->toBe([], 'These private-file routes are reachable without a session.');
});

test('no route we own is registered without a name', function () {
    // An unnamed route cannot be referenced by route() — the frontend then
    // hardcodes a URL, and the next rename breaks it silently. The starter's
    // own auth POST twins and the health check are not ours to rename.
    $starter = ['up', 'settings', 'register', 'login', 'confirm-password', 'broadcasting/auth'];
    $unnamed = [];

    foreach (appRoutes() as $route) {
        if ($route->getName() === null && ! in_array($route->uri(), $starter, true)) {
            $unnamed[] = $route->methods()[0].' '.$route->uri();
        }
    }

    expect($unnamed)->toBe([], 'These routes have no name.');
});
