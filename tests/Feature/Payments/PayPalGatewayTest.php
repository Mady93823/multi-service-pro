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
 * PayPal (D39): Orders v2 over the raw HTTP client. Approval is not money —
 * captureOrder() is, so both the return leg and the APPROVED webhook funnel
 * into it, and webhook authenticity is itself an API call.
 */
const PAYPAL_API = 'api-m.sandbox.paypal.com';

function configurePaypal(): void
{
    $settings = app(SettingsRegistry::class);
    $settings->set('payments.paypal_client_id', 'pp_client_id');
    $settings->set('payments.paypal_client_secret', 'pp_client_secret');
    $settings->set('payments.paypal_webhook_id', 'WH-123');
}

/**
 * @return array{0: User, 1: Booking}
 */
function paypalBooking(): array
{
    $customer = User::factory()->customer()->create();

    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'status' => BookingStatus::PendingPayment,
        'payment_method' => PaymentMethod::Gateway,
    ]);

    return [$customer, $booking];
}

function paypalPayment(Booking $booking, string $orderId): Payment
{
    /** @var Payment $payment */
    $payment = $booking->payments()->create([
        'gateway' => PaymentProvider::PayPal,
        'gateway_ref' => $orderId,
        'amount' => $booking->total,
        'currency' => 'USD',
        'status' => PaymentState::Initiated,
    ]);

    return $payment;
}

function paypalCaptureWebhookBody(string $orderId, string $event = 'PAYMENT.CAPTURE.COMPLETED'): string
{
    return (string) json_encode([
        'event_type' => $event,
        'resource' => [
            'id' => 'CAP-1',
            'supplementary_data' => ['related_ids' => ['order_id' => $orderId]],
        ],
    ]);
}

/**
 * @param  array<string, mixed>  $extra
 */
function paypalHttp(array $extra = [], string $verification = 'SUCCESS'): void
{
    Http::fake(array_merge($extra, [
        PAYPAL_API.'/v1/oauth2/token' => Http::response(['access_token' => 'pp_token', 'expires_in' => 32400]),
        PAYPAL_API.'/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => $verification]),
    ]));
}

test('starting a paypal payment creates one order and hands back the approve link', function () {
    configurePaypal();
    [$customer, $booking] = paypalBooking();

    paypalHttp([
        PAYPAL_API.'/v2/checkout/orders' => Http::response([
            'id' => 'ORDER-1',
            'links' => [
                ['rel' => 'self', 'href' => 'https://api-m.sandbox.paypal.com/v2/checkout/orders/ORDER-1'],
                ['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER-1'],
            ],
        ]),
    ]);

    $this->actingAs($customer)
        ->post(route('payments.initiate', [$booking, 'paypal']))
        ->assertOk()
        ->assertJsonPath('provider', 'paypal')
        ->assertJsonPath('session.url', 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER-1');

    // A refresh reuses the stored order instead of opening a second one.
    $this->actingAs($customer)
        ->post(route('payments.initiate', [$booking, 'paypal']))
        ->assertOk()
        ->assertJsonPath('session.url', 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER-1');

    expect(Payment::query()->where('booking_id', $booking->id)->count())->toBe(1);
    Http::assertSentCount(2); // one token, one order — never a second order
});

test('the paypal return leg settles only when the capture API answers COMPLETED', function () {
    configurePaypal();
    Event::fake([BookingPlaced::class]);
    [$customer, $booking] = paypalBooking();
    $payment = paypalPayment($booking, 'ORDER-2');

    paypalHttp([
        PAYPAL_API.'/v2/checkout/orders/ORDER-2/capture' => Http::response(['id' => 'ORDER-2', 'status' => 'COMPLETED']),
    ]);

    $this->actingAs($customer)
        ->get(route('payments.paypal.return', $booking).'?token=ORDER-2')
        ->assertRedirect(route('bookings.show', $booking))
        ->assertSessionHas('success');

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::Placed)
        ->and($booking->payment_status)->toBe(PaymentStatus::Paid)
        ->and($payment->refresh()->status)->toBe(PaymentState::Captured);

    Event::assertDispatched(BookingPlaced::class);
});

test('a paypal return whose capture does not complete settles nothing', function () {
    configurePaypal();
    [$customer, $booking] = paypalBooking();
    $payment = paypalPayment($booking, 'ORDER-3');

    // A hand-typed return URL: PayPal refuses the capture (never approved).
    paypalHttp([
        PAYPAL_API.'/v2/checkout/orders/ORDER-3/capture' => Http::response([
            'name' => 'UNPROCESSABLE_ENTITY',
            'details' => [['issue' => 'ORDER_NOT_APPROVED']],
        ], 422),
    ]);

    $this->actingAs($customer)
        ->get(route('payments.paypal.return', $booking).'?token=ORDER-3')
        ->assertRedirect(route('bookings.show', $booking))
        ->assertSessionHas('error');

    expect($booking->refresh()->status)->toBe(BookingStatus::PendingPayment)
        ->and($payment->refresh()->status)->toBe(PaymentState::Initiated);
});

test('a paypal return racing an already-captured order confirms from the order itself', function () {
    configurePaypal();
    [$customer, $booking] = paypalBooking();
    paypalPayment($booking, 'ORDER-4');

    paypalHttp([
        PAYPAL_API.'/v2/checkout/orders/ORDER-4/capture' => Http::response([
            'name' => 'UNPROCESSABLE_ENTITY',
            'details' => [['issue' => 'ORDER_ALREADY_CAPTURED']],
        ], 422),
        PAYPAL_API.'/v2/checkout/orders/ORDER-4' => Http::response(['id' => 'ORDER-4', 'status' => 'COMPLETED']),
    ]);

    $this->actingAs($customer)
        ->get(route('payments.paypal.return', $booking).'?token=ORDER-4')
        ->assertRedirect(route('bookings.show', $booking))
        ->assertSessionHas('success');

    expect($booking->refresh()->status)->toBe(BookingStatus::Placed);
});

test('a replayed paypal capture webhook settles the booking exactly once', function () {
    configurePaypal();
    Event::fake([BookingPlaced::class]);
    [, $booking] = paypalBooking();
    $payment = paypalPayment($booking, 'ORDER-5');

    paypalHttp();

    $body = paypalCaptureWebhookBody('ORDER-5');
    $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_PAYPAL-TRANSMISSION-ID' => 'tid'];

    $this->call('POST', route('webhooks.paypal'), [], [], [], $headers, $body)->assertOk();
    $capturedAt = $payment->refresh()->captured_at;

    $this->call('POST', route('webhooks.paypal'), [], [], [], $headers, $body)->assertOk();

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::Placed)
        ->and($booking->payments()->count())->toBe(1)
        ->and($payment->refresh()->captured_at->eq($capturedAt))->toBeTrue()
        ->and($booking->statusHistory()->where('to_status', BookingStatus::Placed->value)->count())->toBe(1);

    Event::assertDispatchedTimes(BookingPlaced::class, 1);
});

test('a paypal webhook failing verification is refused', function () {
    configurePaypal();
    [, $booking] = paypalBooking();
    paypalPayment($booking, 'ORDER-6');

    paypalHttp(verification: 'FAILURE');

    $this->call('POST', route('webhooks.paypal'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], paypalCaptureWebhookBody('ORDER-6'))->assertStatus(400);

    expect($booking->refresh()->status)->toBe(BookingStatus::PendingPayment);
});

test('a paypal webhook is refused when no webhook id is configured', function () {
    // Fail closed: without the webhook id the verify API cannot vouch for
    // anything, so nothing signed or unsigned may settle money.
    $settings = app(SettingsRegistry::class);
    $settings->set('payments.paypal_client_id', 'pp_client_id');
    $settings->set('payments.paypal_client_secret', 'pp_client_secret');

    [, $booking] = paypalBooking();
    paypalPayment($booking, 'ORDER-7');

    Http::fake();

    $this->call('POST', route('webhooks.paypal'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], paypalCaptureWebhookBody('ORDER-7'))->assertStatus(400);

    Http::assertNothingSent();
});

test('an approved-order webhook captures the money the closed browser never did', function () {
    configurePaypal();
    Event::fake([BookingPlaced::class]);
    [, $booking] = paypalBooking();
    $payment = paypalPayment($booking, 'ORDER-8');

    paypalHttp([
        PAYPAL_API.'/v2/checkout/orders/ORDER-8/capture' => Http::response(['id' => 'ORDER-8', 'status' => 'COMPLETED']),
    ]);

    $body = (string) json_encode([
        'event_type' => 'CHECKOUT.ORDER.APPROVED',
        'resource' => ['id' => 'ORDER-8'],
    ]);

    $this->call('POST', route('webhooks.paypal'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::Placed)
        ->and($payment->refresh()->status)->toBe(PaymentState::Captured);

    Event::assertDispatched(BookingPlaced::class);
});

test('a paypal denied capture marks the attempt failed', function () {
    configurePaypal();
    [, $booking] = paypalBooking();
    $payment = paypalPayment($booking, 'ORDER-9');

    paypalHttp();

    $this->call('POST', route('webhooks.paypal'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], paypalCaptureWebhookBody('ORDER-9', 'PAYMENT.CAPTURE.DENIED'))->assertOk();

    expect($booking->refresh()->status)->toBe(BookingStatus::PendingPayment)
        ->and($payment->refresh()->status)->toBe(PaymentState::Failed);
});
