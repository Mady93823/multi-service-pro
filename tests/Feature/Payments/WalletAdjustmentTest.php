<?php

use App\Domain\Payments\WalletService;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\WalletTransaction;

/**
 * M22: support can correct a wallet by hand — through WalletService (D15), so
 * the ledger still reconciles and the reason is on the row.
 */
test('an admin credits a wallet and the ledger stays reconciled', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->customer()->create();

    $this->actingAs($admin)
        ->post(route('admin.customers.wallet', $customer), [
            'direction' => 'credit',
            'amount' => 250,
            'reason' => 'Goodwill for a late job',
        ])
        ->assertRedirect();

    $wallet = app(WalletService::class)->for($customer);

    /** @var WalletTransaction $entry */
    $entry = $wallet->transactions()->latest('id')->firstOrFail();

    expect((float) $wallet->refresh()->balance)->toBe(250.0)
        ->and($entry->type)->toBe('adjustment')
        ->and($entry->direction)->toBe('credit')
        ->and($entry->note)->toBe('Goodwill for a late job')
        ->and((float) $entry->balance_after)->toBe(250.0);

    expect(ActivityLog::query()->where('action', 'wallet.adjust')->where('subject_id', $customer->id)->exists())->toBeTrue();
});

test('a debit that would overdraw the wallet is refused', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->customer()->create();

    app(WalletService::class)->credit($customer, 100, 'refund');

    $this->actingAs($admin)
        ->post(route('admin.customers.wallet', $customer), [
            'direction' => 'debit',
            'amount' => 500,
            'reason' => 'Reversing a credit',
        ])
        ->assertSessionHasErrors('wallet');

    expect(app(WalletService::class)->balance($customer))->toBe(100.0);
});

test('an adjustment needs an amount above zero and a reason', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->customer()->create();

    $this->actingAs($admin)
        ->post(route('admin.customers.wallet', $customer), ['direction' => 'credit', 'amount' => 0, 'reason' => ''])
        ->assertSessionHasErrors(['amount', 'reason']);

    expect(app(WalletService::class)->balance($customer))->toBe(0.0);
});

test('only an admin can adjust a wallet', function () {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)
        ->post(route('admin.customers.wallet', $customer), [
            'direction' => 'credit',
            'amount' => 100,
            'reason' => 'Self-service',
        ])
        ->assertForbidden();

    expect(app(WalletService::class)->balance($customer))->toBe(0.0);
});
