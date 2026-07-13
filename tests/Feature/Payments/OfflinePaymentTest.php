<?php

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Enums\PaymentMethod;
use App\Domain\Bookings\Enums\PaymentStatus;
use App\Domain\Bookings\Events\BookingPlaced;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentState;
use App\Domain\Settings\SettingsRegistry;
use App\Models\BankAccount;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\OfflinePaymentRejectedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * ADR D27: an offline payment is an ordinary payments row that settles through
 * the same ConfirmPayment a gateway webhook calls. These tests exist to keep it
 * that way — no second money path, no second way to place a booking.
 */
beforeEach(function () {
    app(SettingsRegistry::class)->set('payments.offline_enabled', true);
});

function offlineAccount(): BankAccount
{
    return BankAccount::factory()->create(['label' => 'Test current account']);
}

/**
 * @return array{0: User, 1: Booking}
 */
function offlineBooking(): array
{
    $customer = User::factory()->customer()->create();

    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'status' => BookingStatus::PendingPayment,
        'payment_method' => PaymentMethod::Offline,
    ]);

    return [$customer, $booking];
}

function submitProof(User $customer, Booking $booking, BankAccount $account, string $reference = 'UTR12345'): Payment
{
    Storage::fake('local');

    test()->actingAs($customer)
        ->post(route('payments.offline', $booking), [
            'bank_account_id' => $account->id,
            'reference' => $reference,
            'proof' => UploadedFile::fake()->image('receipt.jpg'),
        ])
        ->assertRedirect();

    /** @var Payment $payment */
    $payment = $booking->payments()->latest('id')->firstOrFail();

    return $payment;
}

test('declaring a transfer creates a pending payment and does not place the booking', function () {
    Event::fake([BookingPlaced::class]);

    [$customer, $booking] = offlineBooking();
    $account = offlineAccount();

    $payment = submitProof($customer, $booking, $account);

    expect($payment->gateway)->toBe(PaymentProvider::Offline)
        ->and($payment->status)->toBe(PaymentState::Initiated)
        ->and($payment->bank_account_id)->toBe($account->id)
        ->and($payment->reference)->toBe('UTR12345')
        ->and((float) $payment->amount)->toBe((float) $booking->total)
        ->and($payment->getFirstMedia('proof'))->not->toBeNull();

    // The M08 invariant: an unpaid booking is never dispatched.
    expect($booking->refresh()->status)->toBe(BookingStatus::PendingPayment)
        ->and($booking->payment_status)->toBe(PaymentStatus::Unpaid);

    Event::assertNotDispatched(BookingPlaced::class);
});

test('a second declaration updates the open row instead of queueing another', function () {
    [$customer, $booking] = offlineBooking();
    $account = offlineAccount();

    submitProof($customer, $booking, $account, 'FIRST');
    $payment = submitProof($customer, $booking, $account, 'SECOND');

    expect($booking->payments()->count())->toBe(1)
        ->and($payment->reference)->toBe('SECOND');
});

test('verifying an offline payment places the booking through the one money path', function () {
    Event::fake([BookingPlaced::class]);

    [$customer, $booking] = offlineBooking();
    $admin = User::factory()->admin()->create();
    $payment = submitProof($customer, $booking, offlineAccount());

    $this->actingAs($admin)
        ->post(route('admin.payments.verify', $payment))
        ->assertRedirect();

    $payment->refresh();
    $booking->refresh();

    expect($payment->status)->toBe(PaymentState::Captured)
        ->and($payment->captured_at)->not->toBeNull()
        ->and($payment->reviewed_by)->toBe($admin->id)
        ->and($booking->status)->toBe(BookingStatus::Placed)
        ->and($booking->payment_status)->toBe(PaymentStatus::Paid)
        // The method is not rewritten to `gateway` — it really was a transfer.
        ->and($booking->payment_method)->toBe(PaymentMethod::Offline);

    // Placement is what dispatch listens to (M06) — same event, same path.
    Event::assertDispatched(BookingPlaced::class);
    expect($booking->statusHistory()->where('to_status', BookingStatus::Placed->value)->count())->toBe(1);
});

test('verifying twice settles nothing twice', function () {
    Event::fake([BookingPlaced::class]);

    [$customer, $booking] = offlineBooking();
    $admin = User::factory()->admin()->create();
    $payment = submitProof($customer, $booking, offlineAccount());

    $this->actingAs($admin)->post(route('admin.payments.verify', $payment))->assertRedirect();

    // The double click: ConfirmPayment is row-locked and idempotent, and the
    // action refuses an already-reviewed row before it even gets there.
    $this->actingAs($admin)
        ->post(route('admin.payments.verify', $payment))
        ->assertSessionHasErrors('payment');

    expect($booking->payments()->where('status', PaymentState::Captured->value)->count())->toBe(1)
        ->and($booking->refresh()->statusHistory()->where('to_status', BookingStatus::Placed->value)->count())->toBe(1);

    Event::assertDispatchedTimes(BookingPlaced::class, 1);
});

test('rejecting a transfer fails the payment, notifies the customer and leaves the booking payable', function () {
    Notification::fake();

    [$customer, $booking] = offlineBooking();
    $admin = User::factory()->admin()->create();
    $payment = submitProof($customer, $booking, offlineAccount());

    $this->actingAs($admin)
        ->post(route('admin.payments.reject', $payment), ['reason' => 'No transfer found.'])
        ->assertRedirect();

    $payment->refresh();

    expect($payment->status)->toBe(PaymentState::Failed)
        ->and($payment->failure_reason)->toBe('No transfer found.')
        ->and($payment->reviewed_by)->toBe($admin->id);

    // Rejecting a payment is not a way to cancel a booking: it stays payable
    // and dies on the existing expire-unpaid schedule if nothing else happens.
    expect($booking->refresh()->status)->toBe(BookingStatus::PendingPayment);

    Notification::assertSentTo($customer, OfflinePaymentRejectedNotification::class);
});

test('a rejection must carry a reason', function () {
    [$customer, $booking] = offlineBooking();
    $admin = User::factory()->admin()->create();
    $payment = submitProof($customer, $booking, offlineAccount());

    $this->actingAs($admin)
        ->post(route('admin.payments.reject', $payment), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    expect($payment->refresh()->status)->toBe(PaymentState::Initiated);
});

test('after a rejection the customer can declare a new transfer', function () {
    [$customer, $booking] = offlineBooking();
    $admin = User::factory()->admin()->create();
    $first = submitProof($customer, $booking, offlineAccount(), 'WRONG');

    $this->actingAs($admin)->post(route('admin.payments.reject', $first), ['reason' => 'Not found.']);

    $second = submitProof($customer, $booking, offlineAccount(), 'RIGHT');

    // A new row, not a resurrected one — the failed attempt stays on record.
    expect($second->id)->not->toBe($first->id)
        ->and($booking->payments()->count())->toBe(2)
        ->and($first->refresh()->status)->toBe(PaymentState::Failed);
});

test('a captured gateway payment cannot be verified as an offline one', function () {
    [, $booking] = offlineBooking();
    $admin = User::factory()->admin()->create();

    /** @var Payment $payment */
    $payment = $booking->payments()->create([
        'gateway' => PaymentProvider::Razorpay,
        'gateway_ref' => 'order_x',
        'amount' => $booking->total,
        'currency' => 'INR',
        'status' => PaymentState::Initiated,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.payments.verify', $payment))
        ->assertSessionHasErrors('payment');

    expect($payment->refresh()->status)->toBe(PaymentState::Initiated)
        ->and($booking->refresh()->status)->toBe(BookingStatus::PendingPayment);
});

test('a customer cannot declare a transfer against someone else\'s booking', function () {
    [, $booking] = offlineBooking();
    $stranger = User::factory()->customer()->create();

    $this->actingAs($stranger)
        ->post(route('payments.offline', $booking), [
            'bank_account_id' => offlineAccount()->id,
            'reference' => 'X',
            'proof' => UploadedFile::fake()->image('receipt.jpg'),
        ])
        ->assertNotFound();

    expect($booking->payments()->count())->toBe(0);
});

test('a customer cannot verify their own payment', function () {
    [$customer, $booking] = offlineBooking();
    $payment = submitProof($customer, $booking, offlineAccount());

    $this->actingAs($customer)
        ->post(route('admin.payments.verify', $payment))
        ->assertForbidden();

    expect($payment->refresh()->status)->toBe(PaymentState::Initiated);
});

test('an inactive account cannot be paid into', function () {
    [$customer, $booking] = offlineBooking();
    $account = BankAccount::factory()->inactive()->create();

    $this->actingAs($customer)
        ->post(route('payments.offline', $booking), [
            'bank_account_id' => $account->id,
            'proof' => UploadedFile::fake()->image('receipt.jpg'),
        ])
        ->assertSessionHasErrors('bank_account_id');

    expect($booking->payments()->count())->toBe(0);
});

test('the offline route 404s while the method is switched off', function () {
    app(SettingsRegistry::class)->set('payments.offline_enabled', false);

    [$customer, $booking] = offlineBooking();

    $this->actingAs($customer)
        ->post(route('payments.offline', $booking), [
            'bank_account_id' => offlineAccount()->id,
            'proof' => UploadedFile::fake()->image('receipt.jpg'),
        ])
        ->assertNotFound();

    expect($booking->payments()->count())->toBe(0);
});

test('proof is private: the owner and an admin see it, nobody else does', function () {
    [$customer, $booking] = offlineBooking();
    $payment = submitProof($customer, $booking, offlineAccount());
    $media = $payment->getFirstMedia('proof');

    $url = route('payments.proof.show', [$payment->id, $media?->id]);

    // actingAs() sticks to the guard for the rest of the test — drop it to
    // ask the question a guest would ask.
    $this->app['auth']->forgetGuards();
    $this->get($url)->assertRedirect(route('login'));

    $this->actingAs($customer)->get($url)->assertOk();
    $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
    $this->actingAs(User::factory()->customer()->create())->get($url)->assertForbidden();
    $this->actingAs(User::factory()->provider()->create())->get($url)->assertForbidden();
});
