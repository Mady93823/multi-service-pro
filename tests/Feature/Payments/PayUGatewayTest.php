<?php

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Enums\PaymentMethod;
use App\Domain\Bookings\Enums\PaymentStatus;
use App\Domain\Bookings\Events\BookingPlaced;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentState;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/**
 * PayU (D39): hosted-form checkout, reverse-hash responses, verify_payment as
 * the money proof. The return leg is a cross-site POST with no session, so
 * every test of it runs unauthenticated on purpose.
 */
const PAYU_KEY = 'payu_test_key';
const PAYU_SALT = 'payu_test_salt';

function configurePayu(): void
{
    $settings = app(SettingsRegistry::class);
    $settings->set('payments.payu_key', PAYU_KEY);
    $settings->set('payments.payu_salt', PAYU_SALT);
}

/**
 * @return array{0: User, 1: Booking}
 */
function payuBooking(): array
{
    $customer = User::factory()->customer()->create();

    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'status' => BookingStatus::PendingPayment,
        'payment_method' => PaymentMethod::Gateway,
    ]);

    return [$customer, $booking];
}

function payuPayment(Booking $booking, string $txnid): Payment
{
    /** @var Payment $payment */
    $payment = $booking->payments()->create([
        'gateway' => PaymentProvider::PayU,
        'gateway_ref' => $txnid,
        'amount' => $booking->total,
        'currency' => 'INR',
        'status' => PaymentState::Initiated,
    ]);

    return $payment;
}

/**
 * The response fields PayU would POST back, signed with the reverse hash.
 *
 * @return array<string, string>
 */
function payuResponse(Booking $booking, Payment $payment, string $status = 'success', string $salt = PAYU_SALT): array
{
    $fields = [
        'status' => $status,
        'txnid' => (string) $payment->gateway_ref,
        'amount' => $payment->amount,
        'productinfo' => $booking->code,
        'firstname' => (string) $booking->customer?->name,
        'email' => (string) $booking->customer?->email,
        'udf1' => (string) $booking->id,
        'udf2' => '', 'udf3' => '', 'udf4' => '', 'udf5' => '',
        'mihpayid' => 'mih_123',
    ];

    $fields['hash'] = hash('sha512', implode('|', [
        $salt, $fields['status'],
        '', '', '', '', '',
        $fields['udf5'], $fields['udf4'], $fields['udf3'], $fields['udf2'], $fields['udf1'],
        $fields['email'], $fields['firstname'], $fields['productinfo'],
        $fields['amount'], $fields['txnid'], PAYU_KEY,
    ]));

    return $fields;
}

function payuVerifiedApi(Payment $payment, string $status = 'success'): void
{
    Http::fake([
        'test.payu.in/merchant/postservice.php*' => Http::response([
            'status' => 1,
            'transaction_details' => [(string) $payment->gateway_ref => ['status' => $status]],
        ]),
    ]);
}

test('starting a payu payment signs a form for the test host and reuses the txnid on refresh', function () {
    configurePayu();
    [$customer, $booking] = payuBooking();

    Http::fake();

    $first = $this->actingAs($customer)
        ->post(route('payments.initiate', [$booking, 'payu']))
        ->assertOk()
        ->assertJsonPath('provider', 'payu')
        ->assertJsonPath('session.action', 'https://test.payu.in/_payment')
        ->assertJsonPath('session.fields.key', PAYU_KEY);

    $txnid = $first->json('session.fields.txnid');
    $hash = $first->json('session.fields.hash');

    expect($txnid)->toBeString()->not->toBe('')
        // Pin the request-hash formula: key|txnid|amount|productinfo|firstname|
        // email|udf1..udf5, five reserved empties, salt.
        ->and($hash)->toBe(hash('sha512', implode('|', [
            PAYU_KEY, $txnid, $booking->total, $booking->code,
            $customer->name, $customer->email,
            (string) $booking->id, '', '', '', '',
            '', '', '', '', '',
            PAYU_SALT,
        ])));

    $this->actingAs($customer)
        ->post(route('payments.initiate', [$booking, 'payu']))
        ->assertOk()
        ->assertJsonPath('session.fields.txnid', $txnid);

    expect(Payment::query()->where('booking_id', $booking->id)->count())->toBe(1);
    // The form posts to PayU from the browser — the server never calls out.
    Http::assertNothingSent();
});

test('the payu return leg settles only after the verify API agrees', function () {
    configurePayu();
    Event::fake([BookingPlaced::class]);
    [, $booking] = payuBooking();
    $payment = payuPayment($booking, 'PU1RETURN');
    payuVerifiedApi($payment);

    // No actingAs: PayU posts the customer back cross-site, sessionless.
    $this->post(route('payments.payu.return'), payuResponse($booking, $payment))
        ->assertRedirect(route('bookings.show', $booking->id));

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::Placed)
        ->and($booking->payment_status)->toBe(PaymentStatus::Paid)
        ->and($payment->refresh()->status)->toBe(PaymentState::Captured);

    Event::assertDispatched(BookingPlaced::class);
});

test('a payu return whose verify API disagrees does not settle', function () {
    configurePayu();
    [, $booking] = payuBooking();
    $payment = payuPayment($booking, 'PU1LIES');
    // The hash is genuine but PayU's ledger says the money never moved — a
    // replayed success page must not place the booking (D15).
    payuVerifiedApi($payment, 'failure');

    $this->post(route('payments.payu.return'), payuResponse($booking, $payment))
        ->assertRedirect(route('bookings.show', $booking->id))
        ->assertSessionHas('error');

    expect($booking->refresh()->status)->toBe(BookingStatus::PendingPayment)
        ->and($payment->refresh()->status)->toBe(PaymentState::Initiated);
});

test('a forged payu return hash is rejected outright', function () {
    configurePayu();
    [, $booking] = payuBooking();
    $payment = payuPayment($booking, 'PU1FORGED');

    Http::fake();

    $this->post(route('payments.payu.return'), payuResponse($booking, $payment, salt: 'wrong-salt'))
        ->assertStatus(400);

    expect($booking->refresh()->status)->toBe(BookingStatus::PendingPayment);
    Http::assertNothingSent();
});

test('a payu return for an unknown transaction is a 404', function () {
    configurePayu();

    $this->post(route('payments.payu.return'), ['txnid' => 'PU_NOBODY', 'status' => 'success', 'hash' => 'x'])
        ->assertNotFound();
});

test('a payu failure return marks the attempt failed and the customer can retry', function () {
    configurePayu();
    [, $booking] = payuBooking();
    $payment = payuPayment($booking, 'PU1FAIL');

    $this->post(route('payments.payu.return'), payuResponse($booking, $payment, 'failure'))
        ->assertRedirect(route('bookings.show', $booking->id))
        ->assertSessionHas('error');

    expect($booking->refresh()->status)->toBe(BookingStatus::PendingPayment)
        ->and($payment->refresh()->status)->toBe(PaymentState::Failed);
});

test('a replayed payu webhook settles the booking exactly once', function () {
    configurePayu();
    Event::fake([BookingPlaced::class]);
    [, $booking] = payuBooking();
    $payment = payuPayment($booking, 'PU1HOOK');

    $fields = payuResponse($booking, $payment);
    $body = http_build_query($fields);
    $headers = ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'];

    $this->call('POST', route('webhooks.payu'), [], [], [], $headers, $body)->assertOk();
    $capturedAt = $payment->refresh()->captured_at;

    $this->call('POST', route('webhooks.payu'), [], [], [], $headers, $body)->assertOk();

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::Placed)
        ->and($booking->payments()->count())->toBe(1)
        ->and($payment->refresh()->captured_at->eq($capturedAt))->toBeTrue()
        ->and($booking->statusHistory()->where('to_status', BookingStatus::Placed->value)->count())->toBe(1);

    Event::assertDispatchedTimes(BookingPlaced::class, 1);
});

test('a payu webhook with a bad hash is refused', function () {
    configurePayu();
    [, $booking] = payuBooking();
    $payment = payuPayment($booking, 'PU1BADHASH');

    $body = http_build_query(payuResponse($booking, $payment, salt: 'wrong-salt'));

    $this->call('POST', route('webhooks.payu'), [], [], [], [
        'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
    ], $body)->assertStatus(400);

    expect($booking->refresh()->status)->toBe(BookingStatus::PendingPayment);
});

test('an unsigned payu webhook is refused even when no salt is configured', function () {
    [, $booking] = payuBooking();
    $payment = payuPayment($booking, 'PU1NOSALT');

    $body = http_build_query(payuResponse($booking, $payment));

    $this->call('POST', route('webhooks.payu'), [], [], [], [
        'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
    ], $body)->assertStatus(400);
});
