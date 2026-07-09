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

const RZP_SECRET = 'rzp_secret';
const RZP_HOOK = 'rzp_webhook_secret';
const STRIPE_HOOK = 'stripe_webhook_secret';

function configureRazorpay(): void
{
    $settings = app(SettingsRegistry::class);
    $settings->set('payments.razorpay_key_id', 'rzp_test_key');
    $settings->set('payments.razorpay_key_secret', RZP_SECRET);
    $settings->set('payments.razorpay_webhook_secret', RZP_HOOK);
}

function configureStripe(): void
{
    $settings = app(SettingsRegistry::class);
    $settings->set('payments.stripe_secret_key', 'sk_test_key');
    $settings->set('payments.stripe_publishable_key', 'pk_test_key');
    $settings->set('payments.stripe_webhook_secret', STRIPE_HOOK);
}

/**
 * @return array{0: User, 1: Booking}
 */
function pendingPaymentBooking(): array
{
    $customer = User::factory()->customer()->create();

    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'status' => BookingStatus::PendingPayment,
        'payment_method' => PaymentMethod::Gateway,
    ]);

    return [$customer, $booking];
}

function capturedPayment(Booking $booking, PaymentProvider $provider, string $ref): Payment
{
    /** @var Payment $payment */
    $payment = $booking->payments()->create([
        'gateway' => $provider,
        'gateway_ref' => $ref,
        'amount' => $booking->total,
        'currency' => 'INR',
        'status' => PaymentState::Initiated,
    ]);

    return $payment;
}

function razorpayWebhookBody(string $orderId, string $event = 'payment.captured'): string
{
    return (string) json_encode([
        'event' => $event,
        'payload' => ['payment' => ['entity' => ['id' => 'pay_123', 'order_id' => $orderId]]],
    ]);
}

function stripeSignature(string $body): string
{
    $timestamp = time();

    return "t={$timestamp},v1=".hash_hmac('sha256', $timestamp.'.'.$body, STRIPE_HOOK);
}

test('starting a razorpay payment creates one order and reuses it on refresh', function () {
    configureRazorpay();
    [$customer, $booking] = pendingPaymentBooking();

    Http::fake([
        'api.razorpay.com/v1/orders' => Http::response(['id' => 'order_abc', 'amount' => 59000]),
    ]);

    $this->actingAs($customer)
        ->post(route('payments.initiate', [$booking, 'razorpay']))
        ->assertOk()
        ->assertJsonPath('provider', 'razorpay')
        ->assertJsonPath('session.order_id', 'order_abc')
        ->assertJsonPath('session.key_id', 'rzp_test_key');

    // A page refresh must not open a second upstream order.
    $this->actingAs($customer)
        ->post(route('payments.initiate', [$booking, 'razorpay']))
        ->assertOk()
        ->assertJsonPath('session.order_id', 'order_abc');

    expect(Payment::query()->where('booking_id', $booking->id)->count())->toBe(1);
    Http::assertSentCount(1);
});

test('an unconfigured gateway cannot be started', function () {
    [$customer, $booking] = pendingPaymentBooking();

    $this->actingAs($customer)
        ->post(route('payments.initiate', [$booking, 'razorpay']))
        ->assertSessionHasErrors('payment');

    expect($booking->payments()->count())->toBe(0);
});

test('cash and wallet are not startable as online gateways', function () {
    configureRazorpay();
    [$customer, $booking] = pendingPaymentBooking();

    $this->actingAs($customer)
        ->post(route('payments.initiate', [$booking, 'cash']))
        ->assertNotFound();
});

test('another customer cannot start a payment for someone else’s booking', function () {
    configureRazorpay();
    [, $booking] = pendingPaymentBooking();
    $intruder = User::factory()->customer()->create();

    $this->actingAs($intruder)
        ->post(route('payments.initiate', [$booking, 'razorpay']))
        ->assertNotFound();
});

test('a valid razorpay callback signature captures the payment and places the booking', function () {
    configureRazorpay();
    Event::fake([BookingPlaced::class]);
    [$customer, $booking] = pendingPaymentBooking();
    capturedPayment($booking, PaymentProvider::Razorpay, 'order_abc');

    $signature = hash_hmac('sha256', 'order_abc|pay_123', RZP_SECRET);

    $this->actingAs($customer)
        ->postJson(route('payments.razorpay.callback', $booking), [
            'razorpay_order_id' => 'order_abc',
            'razorpay_payment_id' => 'pay_123',
            'razorpay_signature' => $signature,
        ])
        ->assertOk()
        ->assertJsonPath('redirect', route('bookings.show', $booking));

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::Placed)
        ->and($booking->payment_status)->toBe(PaymentStatus::Paid)
        ->and($booking->payments()->first()->status)->toBe(PaymentState::Captured);

    Event::assertDispatched(BookingPlaced::class);
});

test('a forged razorpay callback signature is rejected and the booking stays unpaid', function () {
    configureRazorpay();
    [$customer, $booking] = pendingPaymentBooking();
    capturedPayment($booking, PaymentProvider::Razorpay, 'order_abc');

    $this->actingAs($customer)
        ->postJson(route('payments.razorpay.callback', $booking), [
            'razorpay_order_id' => 'order_abc',
            'razorpay_payment_id' => 'pay_123',
            'razorpay_signature' => hash_hmac('sha256', 'order_abc|pay_123', 'wrong-secret'),
        ])
        ->assertStatus(422);

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::PendingPayment)
        ->and($booking->payment_status)->toBe(PaymentStatus::Unpaid)
        ->and($booking->payments()->first()->status)->toBe(PaymentState::Initiated);
});

test('a razorpay webhook with a bad signature is refused', function () {
    configureRazorpay();
    [, $booking] = pendingPaymentBooking();
    capturedPayment($booking, PaymentProvider::Razorpay, 'order_abc');

    $body = razorpayWebhookBody('order_abc');

    $this->call('POST', route('webhooks.razorpay'), [], [], [], [
        'HTTP_X-Razorpay-Signature' => hash_hmac('sha256', $body, 'wrong-secret'),
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertStatus(400);

    expect($booking->fresh()->status)->toBe(BookingStatus::PendingPayment);
});

test('an unsigned razorpay webhook is refused even when no secret is configured', function () {
    [, $booking] = pendingPaymentBooking();
    $body = razorpayWebhookBody('order_abc');

    $this->call('POST', route('webhooks.razorpay'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertStatus(400);
});

test('a replayed razorpay webhook settles the booking exactly once', function () {
    configureRazorpay();
    Event::fake([BookingPlaced::class]);
    [, $booking] = pendingPaymentBooking();
    capturedPayment($booking, PaymentProvider::Razorpay, 'order_abc');

    $body = razorpayWebhookBody('order_abc');
    $headers = [
        'HTTP_X-Razorpay-Signature' => hash_hmac('sha256', $body, RZP_HOOK),
        'CONTENT_TYPE' => 'application/json',
    ];

    $this->call('POST', route('webhooks.razorpay'), [], [], [], $headers, $body)->assertOk();
    $capturedAt = $booking->payments()->first()->captured_at;

    // Gateways retry; the second delivery must change nothing.
    $this->call('POST', route('webhooks.razorpay'), [], [], [], $headers, $body)->assertOk();

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::Placed)
        ->and($booking->payments()->count())->toBe(1)
        ->and($booking->payments()->first()->captured_at->eq($capturedAt))->toBeTrue()
        ->and($booking->statusHistory()->where('to_status', BookingStatus::Placed->value)->count())->toBe(1);

    Event::assertDispatchedTimes(BookingPlaced::class, 1);
});

test('a failed razorpay payment marks the attempt failed without placing the booking', function () {
    configureRazorpay();
    [, $booking] = pendingPaymentBooking();
    capturedPayment($booking, PaymentProvider::Razorpay, 'order_abc');

    $body = razorpayWebhookBody('order_abc', 'payment.failed');

    $this->call('POST', route('webhooks.razorpay'), [], [], [], [
        'HTTP_X-Razorpay-Signature' => hash_hmac('sha256', $body, RZP_HOOK),
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::PendingPayment)
        ->and($booking->payments()->first()->status)->toBe(PaymentState::Failed);
});

test('a webhook for an unknown reference is acknowledged and ignored', function () {
    configureRazorpay();
    $body = razorpayWebhookBody('order_nobody_knows');

    $this->call('POST', route('webhooks.razorpay'), [], [], [], [
        'HTTP_X-Razorpay-Signature' => hash_hmac('sha256', $body, RZP_HOOK),
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk()->assertJsonPath('received', true);
});

test('the stripe return leg trusts the API, not the redirect', function () {
    configureStripe();
    [$customer, $booking] = pendingPaymentBooking();
    capturedPayment($booking, PaymentProvider::Stripe, 'cs_test_1');

    // Stripe says the session is NOT paid — a hand-typed return URL must not settle it.
    Http::fake([
        'api.stripe.com/v1/checkout/sessions/cs_test_1' => Http::response(['payment_status' => 'unpaid']),
    ]);

    $this->actingAs($customer)
        ->get(route('payments.stripe.return', $booking).'?session_id=cs_test_1')
        ->assertRedirect(route('bookings.show', $booking))
        ->assertSessionHas('error');

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::PendingPayment)
        ->and($booking->payment_status)->toBe(PaymentStatus::Unpaid);
});

test('the stripe return leg settles when the API confirms payment', function () {
    configureStripe();
    [$customer, $booking] = pendingPaymentBooking();
    capturedPayment($booking, PaymentProvider::Stripe, 'cs_test_1');

    Http::fake([
        'api.stripe.com/v1/checkout/sessions/cs_test_1' => Http::response(['payment_status' => 'paid']),
    ]);

    $this->actingAs($customer)
        ->get(route('payments.stripe.return', $booking).'?session_id=cs_test_1')
        ->assertRedirect(route('bookings.show', $booking))
        ->assertSessionHas('success');

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::Placed)
        ->and($booking->payment_status)->toBe(PaymentStatus::Paid);
});

test('a stripe webhook with a bad signature is refused', function () {
    configureStripe();
    [, $booking] = pendingPaymentBooking();
    capturedPayment($booking, PaymentProvider::Stripe, 'cs_test_1');

    $body = (string) json_encode([
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_test_1', 'payment_status' => 'paid']],
    ]);

    $this->call('POST', route('webhooks.stripe'), [], [], [], [
        'HTTP_Stripe-Signature' => 't='.time().',v1=deadbeef',
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertStatus(400);

    expect($booking->fresh()->status)->toBe(BookingStatus::PendingPayment);
});

test('a signed stripe checkout.session.completed webhook places the booking', function () {
    configureStripe();
    [, $booking] = pendingPaymentBooking();
    capturedPayment($booking, PaymentProvider::Stripe, 'cs_test_1');

    $body = (string) json_encode([
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_test_1', 'payment_status' => 'paid']],
    ]);

    $this->call('POST', route('webhooks.stripe'), [], [], [], [
        'HTTP_Stripe-Signature' => stripeSignature($body),
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::Placed)
        ->and($booking->payment_status)->toBe(PaymentStatus::Paid);
});

test('money captured against an expired booking is kept for a support refund', function () {
    configureRazorpay();
    [, $booking] = pendingPaymentBooking();
    capturedPayment($booking, PaymentProvider::Razorpay, 'order_abc');

    // The expiry command ran while the customer was still on the gateway page.
    $booking->forceFill(['status' => BookingStatus::Expired])->save();

    $body = razorpayWebhookBody('order_abc');

    $this->call('POST', route('webhooks.razorpay'), [], [], [], [
        'HTTP_X-Razorpay-Signature' => hash_hmac('sha256', $body, RZP_HOOK),
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    $booking->refresh();

    // Status is untouched, but the money is on record and the booking reads as
    // paid — the admin booking screen offers the refund.
    expect($booking->status)->toBe(BookingStatus::Expired)
        ->and($booking->payment_status)->toBe(PaymentStatus::Paid)
        ->and($booking->payments()->first()->status)->toBe(PaymentState::Captured);
});
