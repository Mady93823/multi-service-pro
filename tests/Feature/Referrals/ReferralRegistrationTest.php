<?php

use App\Domain\Referrals\Enums\ReferralStatus;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Referral;
use App\Models\User;

function referrer(string $code = 'FRIEND01'): User
{
    $referrer = User::factory()->customer()->create();
    $referrer->forceFill(['referral_code' => $code])->save();

    return $referrer;
}

function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'New Customer',
        'email' => 'newbie@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], $overrides);
}

test('registering with a referral code creates a pending referral', function () {
    $referrer = referrer();

    $this->post(route('register'), registrationPayload(['referral_code' => 'friend01']))
        ->assertRedirect(route('dashboard'));

    $referee = User::query()->where('email', 'newbie@example.com')->sole();
    $referral = Referral::query()->where('referee_id', $referee->id)->sole();

    expect($referral->referrer_id)->toBe($referrer->id)
        ->and($referral->status)->toBe(ReferralStatus::Pending)
        ->and($referral->code_used)->toBe('FRIEND01')
        ->and($referral->reward_amount)->toBeNull();
});

test('an unknown referral code fails validation', function () {
    $this->post(route('register'), registrationPayload(['referral_code' => 'GHOST999']))
        ->assertSessionHasErrors('referral_code');

    expect(User::query()->where('email', 'newbie@example.com')->exists())->toBeFalse();
});

test('registration without a code creates no referral', function () {
    $this->post(route('register'), registrationPayload())->assertRedirect(route('dashboard'));

    $referee = User::query()->where('email', 'newbie@example.com')->sole();
    expect(Referral::query()->where('referee_id', $referee->id)->exists())->toBeFalse();
});

test('a disabled referral program ignores the code entirely', function () {
    referrer();
    app(SettingsRegistry::class)->set('referrals.enabled', false);

    $this->post(route('register'), registrationPayload(['referral_code' => 'FRIEND01']))
        ->assertRedirect(route('dashboard'));

    $referee = User::query()->where('email', 'newbie@example.com')->sole();
    expect(Referral::query()->where('referee_id', $referee->id)->exists())->toBeFalse();
});

test('the register page exposes the program flag and the ?ref= prefill', function () {
    $this->get(route('register', ['ref' => 'abc123']))->assertInertia(
        fn ($page) => $page
            ->component('auth/register')
            ->where('referrals_enabled', true)
            ->where('referral_code', 'ABC123')
    );
});

test('the wallet page hands out a share code and lists referrals', function () {
    $referrer = referrer('MYCODE12');
    $referee = User::factory()->customer()->create();
    Referral::factory()->create([
        'referrer_id' => $referrer->id,
        'referee_id' => $referee->id,
        'code_used' => 'MYCODE12',
    ]);

    $this->actingAs($referrer)->get(route('wallet.show'))->assertInertia(
        fn ($page) => $page
            ->component('customer/wallet')
            ->where('referrals.code', 'MYCODE12')
            ->where('referrals.entries.0.status', 'pending')
    );
});

test('the wallet page generates a code for a user who has none', function () {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)->get(route('wallet.show'))->assertInertia(
        fn ($page) => $page->where('referrals.code', fn (string $code): bool => strlen($code) === 8)
    );

    expect($customer->refresh()->referral_code)->not->toBeNull();
});
