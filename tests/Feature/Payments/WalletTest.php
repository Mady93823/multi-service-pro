<?php

use App\Domain\Bookings\Actions\CancelBooking;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Enums\PaymentMethod;
use App\Domain\Bookings\Enums\PaymentStatus;
use App\Domain\Bookings\Events\BookingPlaced;
use App\Domain\Payments\Actions\PayWithWallet;
use App\Domain\Payments\Actions\RefundBookingToWallet;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentState;
use App\Domain\Payments\WalletService;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Booking;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

/**
 * @return array{0: User, 1: Booking}
 */
function walletBooking(string $balance = '1000.00', BookingStatus $status = BookingStatus::PendingPayment): array
{
    $customer = User::factory()->customer()->create();

    if ($balance !== '0.00') {
        app(WalletService::class)->credit($customer, $balance, 'topup');
    }

    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'status' => $status,
        'payment_method' => PaymentMethod::Wallet,
    ]);

    return [$customer, $booking];
}

/** balance == sum(credits) − sum(debits), always. */
function assertLedgerReconciles(User $customer): void
{
    $wallet = app(WalletService::class)->for($customer);

    $credits = (float) $wallet->transactions()->where('direction', 'credit')->sum('amount');
    $debits = (float) $wallet->transactions()->where('direction', 'debit')->sum('amount');

    expect(round((float) $wallet->fresh()->balance, 2))->toBe(round($credits - $debits, 2));
}

test('every ledger entry carries the running balance and reconciles', function () {
    $customer = User::factory()->customer()->create();
    $wallet = app(WalletService::class);

    $wallet->credit($customer, '500.00', 'topup');
    $wallet->credit($customer, '250.50', 'refund');
    $wallet->debit($customer, '100.25', 'payment');

    $entries = WalletTransaction::query()->orderBy('id')->get();

    expect($entries->pluck('balance_after')->all())->toBe(['500.00', '750.50', '650.25']);
    expect($wallet->balance($customer))->toBe(650.25);

    assertLedgerReconciles($customer);
});

test('a wallet movement of zero or less is refused', function () {
    $customer = User::factory()->customer()->create();

    expect(fn () => app(WalletService::class)->credit($customer, '0.00', 'topup'))
        ->toThrow(ValidationException::class);
});

test('paying from the wallet places the booking and debits the ledger once', function () {
    Event::fake([BookingPlaced::class]);
    [$customer, $booking] = walletBooking('1000.00');

    app(PayWithWallet::class)->handle($booking, $customer);

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::Placed)
        ->and($booking->payment_status)->toBe(PaymentStatus::Paid)
        ->and($booking->payment_method)->toBe(PaymentMethod::Wallet)
        ->and(app(WalletService::class)->balance($customer))->toBe(410.0) // 1000 − 590
        ->and($booking->payments()->first()->gateway)->toBe(PaymentProvider::Wallet)
        ->and($booking->payments()->first()->status)->toBe(PaymentState::Captured);

    assertLedgerReconciles($customer);
    Event::assertDispatchedTimes(BookingPlaced::class, 1);
});

test('an insufficient wallet balance leaves the booking pending and the ledger untouched', function () {
    [$customer, $booking] = walletBooking('100.00');

    expect(fn () => app(PayWithWallet::class)->handle($booking, $customer))
        ->toThrow(ValidationException::class);

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::PendingPayment)
        ->and($booking->payment_status)->toBe(PaymentStatus::Unpaid)
        ->and($booking->payments()->count())->toBe(0)
        ->and(app(WalletService::class)->balance($customer))->toBe(100.0);

    // The rolled-back attempt left no debit behind.
    assertLedgerReconciles($customer);
});

test('the wallet cannot be used when the setting is switched off', function () {
    [$customer, $booking] = walletBooking('1000.00');
    app(SettingsRegistry::class)->set('payments.wallet_enabled', false);

    expect(fn () => app(PayWithWallet::class)->handle($booking, $customer))
        ->toThrow(ValidationException::class);

    expect($booking->fresh()->status)->toBe(BookingStatus::PendingPayment);
});

test('a booking that is not awaiting payment cannot be paid again', function () {
    [$customer, $booking] = walletBooking('1000.00', BookingStatus::Placed);

    expect(fn () => app(PayWithWallet::class)->handle($booking, $customer))
        ->toThrow(ValidationException::class);
});

test('the pay-with-wallet route settles and redirects to the booking', function () {
    [$customer, $booking] = walletBooking('1000.00');

    $this->actingAs($customer)
        ->post(route('payments.wallet', $booking))
        ->assertRedirect(route('bookings.show', $booking))
        ->assertSessionHas('success');

    expect($booking->fresh()->status)->toBe(BookingStatus::Placed);
});

test('cancelling a paid booking refunds the total minus the cancellation fee', function () {
    [$customer, $booking] = walletBooking('1000.00');

    app(PayWithWallet::class)->handle($booking, $customer);
    $booking->refresh();

    // Fee is a percent of total by default; force a known flat one. The
    // factory books 48h out, so a 72h free window puts us inside the fee zone.
    $settings = app(SettingsRegistry::class);
    $settings->set('booking.cancellation_fee_type', 'flat');
    $settings->set('booking.cancellation_fee_value', '90');
    $settings->set('booking.free_cancel_hours', 72);

    app(CancelBooking::class)->byCustomer($booking, $customer, 'Changed my mind');

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::CancelledCustomer)
        ->and($booking->cancellation_fee)->toBe('90.00')
        ->and($booking->payment_status)->toBe(PaymentStatus::PartialRefund)
        // 1000 − 590 paid, then 500 refunded (590 − 90 fee).
        ->and(app(WalletService::class)->balance($customer))->toBe(910.0);

    assertLedgerReconciles($customer);
});

test('an admin cancellation refunds a paid booking in full', function () {
    [$customer, $booking] = walletBooking('1000.00');
    $admin = User::factory()->admin()->create();

    app(PayWithWallet::class)->handle($booking, $customer);
    $booking->refresh();

    app(CancelBooking::class)->byAdmin($booking, $admin, 'Provider unavailable');

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::CancelledAdmin)
        ->and($booking->payment_status)->toBe(PaymentStatus::Refunded)
        ->and(app(WalletService::class)->balance($customer))->toBe(1000.0)
        ->and($booking->payments()->first()->status)->toBe(PaymentState::Refunded);

    assertLedgerReconciles($customer);
});

test('cancelling an unpaid cash booking refunds nothing', function () {
    $customer = User::factory()->customer()->create();
    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'status' => BookingStatus::Placed,
        'payment_method' => PaymentMethod::Cash,
    ]);

    app(CancelBooking::class)->byCustomer($booking, $customer, 'Changed my mind');

    expect(app(WalletService::class)->balance($customer))->toBe(0.0)
        ->and($booking->fresh()->payment_status)->toBe(PaymentStatus::Unpaid);
});

test('an unpaid booking cannot be refunded', function () {
    [, $booking] = walletBooking('0.00');

    expect(fn () => app(RefundBookingToWallet::class)->handle($booking))
        ->toThrow(ValidationException::class);
});

test('a refund larger than the captured amount is refused', function () {
    [$customer, $booking] = walletBooking('1000.00');
    app(PayWithWallet::class)->handle($booking, $customer);

    expect(fn () => app(RefundBookingToWallet::class)->handle($booking->fresh(), 10_000.0))
        ->toThrow(ValidationException::class);
});

test('an admin can refund a paid booking from the booking screen', function () {
    [$customer, $booking] = walletBooking('1000.00');
    $admin = User::factory()->admin()->create();

    app(PayWithWallet::class)->handle($booking, $customer);

    $this->actingAs($admin)
        ->post(route('admin.bookings.refund', $booking))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($booking->fresh()->payment_status)->toBe(PaymentStatus::Refunded)
        ->and(app(WalletService::class)->balance($customer))->toBe(1000.0);

    assertLedgerReconciles($customer);
});

test('a customer cannot reach the admin refund action', function () {
    [$customer, $booking] = walletBooking('1000.00');
    app(PayWithWallet::class)->handle($booking, $customer);

    $this->actingAs($customer)
        ->post(route('admin.bookings.refund', $booking))
        ->assertForbidden();
});

test('the wallet page lists the ledger newest first', function () {
    $customer = User::factory()->customer()->create();
    app(WalletService::class)->credit($customer, '500.00', 'topup');
    app(WalletService::class)->credit($customer, '250.00', 'refund');

    $this->actingAs($customer)
        ->get(route('wallet.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('customer/wallet')
            ->where('balance', '750.00')
            ->where('transactions.data.0.type', 'refund')
            ->where('transactions.data.1.type', 'topup'),
        );
});
