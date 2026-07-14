<?php

use Illuminate\Routing\Route as RouteInstance;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/**
 * A sweep, not a sample (§07 Testing). Every module remembers to throttle the
 * endpoint it *knows* is dangerous; the one that gets forgotten is the ordinary
 * POST that happens to need no session — and that is the only kind an attacker
 * can hit in a loop for free.
 */
test('every state-changing route reachable without a session is rate limited', function () {
    // The framework registers this one and authorizes per channel inside
    // routes/channels.php; a session is not what guards it.
    $frameworkOwned = ['broadcasting/auth'];

    $writeVerbs = ['POST', 'PUT', 'PATCH', 'DELETE'];
    $unthrottled = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        assert($route instanceof RouteInstance);

        $uri = $route->uri();

        if (str_starts_with($uri, '_') || in_array($uri, $frameworkOwned, true)) {
            continue;
        }

        if (array_intersect($route->methods(), $writeVerbs) === []) {
            continue;
        }

        $middleware = $route->gatherMiddleware();

        if (in_array('auth', $middleware, true)) {
            continue;
        }

        $throttled = array_filter(
            $middleware,
            fn ($item): bool => is_string($item) && str_starts_with($item, 'throttle:'),
        );

        if ($throttled === []) {
            $unthrottled[] = $route->methods()[0].' '.$uri;
        }
    }

    expect($unthrottled)->toBe([], 'These routes change state, need no session, and have no rate limit.');
});

test('the named limiters exist', function () {
    // A route saying `throttle:uploads` when no `uploads` limiter is defined does
    // not fail — Laravel treats the name as "unlimited". The typo is invisible.
    foreach (['auth', 'uploads', 'public-write'] as $name) {
        expect(RateLimiter::limiter($name))->not->toBeNull("The `{$name}` rate limiter is not defined.");
    }
});

test('registration is capped per IP', function () {
    // Account creation is free for an attacker and permanent for us.
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->post(route('register'), [
            'name' => 'Bot '.$attempt,
            'email' => "bot{$attempt}@example.test",
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->post(route('logout'));
    }

    $this->post(route('register'), [
        'name' => 'Bot 6',
        'email' => 'bot6@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertStatus(429);
});

test('password reset mail cannot be posted to an address in a loop', function () {
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->post(route('password.email'), ['email' => 'victim@example.test']);
    }

    $this->post(route('password.email'), ['email' => 'victim@example.test'])
        ->assertStatus(429);
});
