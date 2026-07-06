<?php

use App\Models\User;

test('each role can reach its own dashboard', function (string $factoryState, string $routeName) {
    $user = User::factory()->{$factoryState}()->create();

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertOk();
})->with([
    'customer' => ['customer', 'dashboard'],
    'provider' => ['provider', 'provider.dashboard'],
    'admin' => ['admin', 'admin.dashboard'],
]);

test('cross-role access is blocked', function (string $factoryState, string $routeName) {
    $user = User::factory()->{$factoryState}()->create();

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertForbidden();
})->with([
    'customer to provider' => ['customer', 'provider.dashboard'],
    'customer to admin' => ['customer', 'admin.dashboard'],
    'provider to customer' => ['provider', 'dashboard'],
    'provider to admin' => ['provider', 'admin.dashboard'],
    'admin to customer' => ['admin', 'dashboard'],
    'admin to provider' => ['admin', 'provider.dashboard'],
]);

test('guests are redirected to login from role dashboards', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with([
    'provider' => 'provider.dashboard',
    'admin' => 'admin.dashboard',
]);

test('login redirects each role to its own dashboard', function (string $factoryState, string $routeName) {
    $user = User::factory()->{$factoryState}()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route($routeName, absolute: false));
})->with([
    'customer' => ['customer', 'dashboard'],
    'provider' => ['provider', 'provider.dashboard'],
    'admin' => ['admin', 'admin.dashboard'],
]);

test('login records last_login_at', function () {
    $user = User::factory()->customer()->create();

    expect($user->last_login_at)->toBeNull();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    expect($user->refresh()->last_login_at)->not->toBeNull();
});

test('registration assigns the customer role', function () {
    $this->post('/register', [
        'name' => 'New Customer',
        'email' => 'new-customer@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::query()->where('email', 'new-customer@example.com')->firstOrFail();

    expect($user->hasRole('customer'))->toBeTrue();
});

test('deactivated users cannot log in', function () {
    $user = User::factory()->customer()->inactive()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});
