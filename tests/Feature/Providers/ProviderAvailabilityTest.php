<?php

use App\Models\ProviderProfile;
use App\Models\User;

function availabilityProvider(bool $approved = true): User
{
    $user = User::factory()->provider()->create();

    $factory = ProviderProfile::factory()->for($user);
    ($approved ? $factory->approved() : $factory)->create();

    return $user;
}

test('an approved provider can toggle online and back off', function () {
    $provider = availabilityProvider();

    $this->actingAs($provider)->post(route('provider.availability.online'))->assertSessionHasNoErrors();
    expect($provider->providerProfile()->firstOrFail()->is_online)->toBeTrue();

    $this->actingAs($provider)->post(route('provider.availability.online'));
    expect($provider->providerProfile()->firstOrFail()->is_online)->toBeFalse();
});

test('a pending provider cannot reach availability controls', function () {
    $provider = availabilityProvider(approved: false);

    $this->actingAs($provider)
        ->post(route('provider.availability.online'))
        ->assertRedirect(route('provider.onboarding'));
});

test('a provider can plan and remove time off', function () {
    $provider = availabilityProvider();

    $this->actingAs($provider)
        ->post(route('provider.blackouts.store'), [
            'starts_on' => now()->addDays(5)->toDateString(),
            'ends_on' => now()->addDays(7)->toDateString(),
            'reason' => 'Festival',
        ])
        ->assertSessionHasNoErrors();

    $blackout = $provider->providerProfile()->firstOrFail()->blackouts()->firstOrFail();
    expect($blackout->reason)->toBe('Festival');

    $this->actingAs($provider)->delete(route('provider.blackouts.destroy', $blackout))->assertRedirect();
    expect($provider->providerProfile()->firstOrFail()->blackouts()->count())->toBe(0);
});

test('time off cannot end before it starts or start in the past', function () {
    $provider = availabilityProvider();

    $this->actingAs($provider)
        ->post(route('provider.blackouts.store'), [
            'starts_on' => now()->addDays(7)->toDateString(),
            'ends_on' => now()->addDays(5)->toDateString(),
        ])
        ->assertSessionHasErrors(['ends_on']);

    $this->actingAs($provider)
        ->post(route('provider.blackouts.store'), [
            'starts_on' => now()->subDay()->toDateString(),
            'ends_on' => now()->addDay()->toDateString(),
        ])
        ->assertSessionHasErrors(['starts_on']);
});

test('a provider cannot delete someone else\'s time off', function () {
    $owner = availabilityProvider();
    $stranger = availabilityProvider();

    $this->actingAs($owner)->post(route('provider.blackouts.store'), [
        'starts_on' => now()->addDays(3)->toDateString(),
        'ends_on' => now()->addDays(4)->toDateString(),
    ]);

    $blackout = $owner->providerProfile()->firstOrFail()->blackouts()->firstOrFail();

    $this->actingAs($stranger)->delete(route('provider.blackouts.destroy', $blackout))->assertNotFound();
    expect($owner->providerProfile()->firstOrFail()->blackouts()->count())->toBe(1);
});
