<?php

use App\Domain\Earnings\Enums\EarningStatus;
use App\Domain\Earnings\Enums\PayoutStatus;
use App\Models\Earning;
use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Models\ProviderProfile;
use App\Models\User;

/**
 * M22: a payout goes to a stored account, not to whatever was typed into the
 * dialog that day. The request still snapshots the details it used — money rows
 * are snapshots (M09), so editing the account later cannot rewrite history.
 */
function accountProvider(): User
{
    $provider = User::factory()->provider()->create();
    ProviderProfile::factory()->approved()->online()->for($provider)->create();

    return $provider;
}

function accountEarning(User $provider, float $net = 1000.0): Earning
{
    return Earning::factory()->create([
        'provider_id' => $provider->id,
        'gross' => $net,
        'commission' => 0,
        'net' => $net,
        'status' => EarningStatus::Available,
        'available_at' => now()->subDay(),
        'payout_request_id' => null,
    ]);
}

test('a provider saves an account and requests a payout against it', function () {
    $provider = accountProvider();
    accountEarning($provider);

    $this->actingAs($provider)
        ->post(route('provider.payout-accounts.store'), [
            'type' => 'upi',
            'label' => 'Primary',
            'upi_id' => 'provider@upi',
            'is_default' => true,
        ])
        ->assertRedirect();

    /** @var PayoutAccount $account */
    $account = PayoutAccount::query()->where('provider_id', $provider->id)->firstOrFail();

    $this->actingAs($provider)
        ->post(route('provider.payouts.store'), ['payout_account_id' => $account->id])
        ->assertRedirect();

    /** @var PayoutRequest $payout */
    $payout = PayoutRequest::query()->where('provider_id', $provider->id)->firstOrFail();

    expect($payout->payout_account_id)->toBe($account->id)
        ->and($payout->method_details)->toBe(['method' => 'upi', 'upi_id' => 'provider@upi'])
        ->and($payout->status)->toBe(PayoutStatus::Requested);
});

test('the stored snapshot survives a later edit to the account', function () {
    $provider = accountProvider();
    accountEarning($provider);

    $account = PayoutAccount::factory()->create(['provider_id' => $provider->id, 'upi_id' => 'old@upi']);

    $this->actingAs($provider)->post(route('provider.payouts.store'), ['payout_account_id' => $account->id]);

    $this->actingAs($provider)->put(route('provider.payout-accounts.update', $account), [
        'type' => 'upi',
        'upi_id' => 'new@upi',
        'is_default' => true,
    ]);

    /** @var PayoutRequest $payout */
    $payout = PayoutRequest::query()->where('provider_id', $provider->id)->firstOrFail();

    expect($payout->method_details['upi_id'])->toBe('old@upi')
        ->and($account->refresh()->upi_id)->toBe('new@upi');
});

test('editing an account clears its verified tick', function () {
    $provider = accountProvider();
    $account = PayoutAccount::factory()->verified()->create(['provider_id' => $provider->id]);

    $this->actingAs($provider)->put(route('provider.payout-accounts.update', $account), [
        'type' => 'upi',
        'upi_id' => 'changed@upi',
        'is_default' => true,
    ])->assertRedirect();

    expect($account->refresh()->is_verified)->toBeFalse()
        ->and($account->verified_at)->toBeNull();
});

test('a bank account keeps only bank fields, a upi account only the upi id', function () {
    $provider = accountProvider();

    $this->actingAs($provider)->post(route('provider.payout-accounts.store'), [
        'type' => 'bank',
        'account_name' => 'A Provider',
        'account_number' => '123456789',
        'ifsc' => 'HDFC0001234',
        // A stale UPI id from the other half of the form must not be stored.
        'upi_id' => 'leftover@upi',
        'is_default' => true,
    ])->assertRedirect();

    /** @var PayoutAccount $account */
    $account = PayoutAccount::query()->where('provider_id', $provider->id)->firstOrFail();

    expect($account->type)->toBe('bank')
        ->and($account->upi_id)->toBeNull()
        ->and($account->account_number)->toBe('123456789')
        ->and($account->toSnapshot())->toBe([
            'method' => 'bank',
            'account_name' => 'A Provider',
            'account_number' => '123456789',
            'ifsc' => 'HDFC0001234',
        ]);
});

test('only one account is the default', function () {
    $provider = accountProvider();

    $first = PayoutAccount::factory()->create(['provider_id' => $provider->id, 'is_default' => true]);

    $this->actingAs($provider)->post(route('provider.payout-accounts.store'), [
        'type' => 'upi',
        'upi_id' => 'second@upi',
        'is_default' => true,
    ])->assertRedirect();

    expect($first->refresh()->is_default)->toBeFalse()
        ->and(PayoutAccount::query()->where('provider_id', $provider->id)->where('is_default', true)->count())->toBe(1);
});

test('a provider cannot pay out to an account that is not theirs', function () {
    $provider = accountProvider();
    accountEarning($provider);

    $other = PayoutAccount::factory()->create(['provider_id' => accountProvider()->id]);

    $this->actingAs($provider)
        ->post(route('provider.payouts.store'), ['payout_account_id' => $other->id])
        ->assertSessionHasErrors('payout_account_id');

    expect(PayoutRequest::query()->where('provider_id', $provider->id)->exists())->toBeFalse();
});

test('a provider cannot edit or delete someone else\'s account', function () {
    $provider = accountProvider();
    $other = PayoutAccount::factory()->create(['provider_id' => accountProvider()->id]);

    $this->actingAs($provider)
        ->put(route('provider.payout-accounts.update', $other), ['type' => 'upi', 'upi_id' => 'hijack@upi'])
        ->assertNotFound();

    $this->actingAs($provider)
        ->delete(route('provider.payout-accounts.destroy', $other))
        ->assertNotFound();

    expect($other->refresh()->upi_id)->not->toBe('hijack@upi');
});

test('an account cannot be deleted while a payout is riding on it', function () {
    $provider = accountProvider();
    accountEarning($provider);

    $account = PayoutAccount::factory()->create(['provider_id' => $provider->id]);

    $this->actingAs($provider)->post(route('provider.payouts.store'), ['payout_account_id' => $account->id]);

    $this->actingAs($provider)
        ->delete(route('provider.payout-accounts.destroy', $account))
        ->assertSessionHasErrors('payout_account');

    expect(PayoutAccount::query()->whereKey($account->id)->exists())->toBeTrue();
});

test('an admin verifies a payout account, and it shows on the payout queue', function () {
    $admin = User::factory()->admin()->create();
    $provider = accountProvider();
    accountEarning($provider);

    $account = PayoutAccount::factory()->create(['provider_id' => $provider->id]);

    $this->actingAs($provider)->post(route('provider.payouts.store'), ['payout_account_id' => $account->id]);

    $this->actingAs($admin)
        ->post(route('admin.payout-accounts.verify', $account), ['verified' => true])
        ->assertRedirect();

    expect($account->refresh()->is_verified)->toBeTrue()
        ->and($account->verified_at)->not->toBeNull();

    $this->actingAs($admin)->get(route('admin.payouts.index'))->assertOk();
});

test('a provider cannot verify their own account', function () {
    $provider = accountProvider();
    $account = PayoutAccount::factory()->create(['provider_id' => $provider->id]);

    $this->actingAs($provider)
        ->post(route('admin.payout-accounts.verify', $account), ['verified' => true])
        ->assertForbidden();

    expect($account->refresh()->is_verified)->toBeFalse();
});
