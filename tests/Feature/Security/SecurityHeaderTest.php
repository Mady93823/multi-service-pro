<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

test('every response carries the browser-side defences', function () {
    $response = $this->get(route('home'))->assertOk();

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    // Geolocation is the one device API the product needs — the provider journey
    // screen reads GPS (M07). A policy that withheld it would kill live tracking
    // silently, which is exactly the kind of thing a header gets wrong.
    expect($response->headers->get('Permissions-Policy'))
        ->toContain('geolocation=(self)')
        ->toContain('camera=()');
});

test('a redirect out of a guard is protected too', function () {
    // The headers are appended last on purpose: a middleware that short-circuits
    // (auth, EnsureUserActive, EnsureInstalled) still returns through them.
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('HSTS goes out only on a secure production request', function () {
    $middleware = new SecurityHeaders;
    $next = fn (): Response => new Response('ok');

    $secure = Request::create('https://example.test/', 'GET');
    $plain = Request::create('http://example.test/', 'GET');

    app()['env'] = 'production';

    expect($middleware->handle($secure, $next)->headers->has('Strict-Transport-Security'))->toBeTrue()
        // Sent over plain HTTP it means nothing; sent in development it would pin
        // the developer's own browser to https://localhost with no way back.
        ->and($middleware->handle($plain, $next)->headers->has('Strict-Transport-Security'))->toBeFalse();

    app()['env'] = 'testing';

    expect($middleware->handle($secure, $next)->headers->has('Strict-Transport-Security'))->toBeFalse();
});
