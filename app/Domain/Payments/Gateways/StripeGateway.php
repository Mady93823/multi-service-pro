<?php

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\WebhookResult;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Stripe Checkout via its REST API (M08, secondary gateway — D8 product
 * requirement for non-India buyers). Hosted page: we create a session and
 * redirect; confirmation comes from the checkout.session.completed webhook,
 * with the return URL double-checked against the API (never trust the
 * redirect alone).
 */
class StripeGateway implements PaymentGateway
{
    private const API = 'https://api.stripe.com/v1';

    public function __construct(private readonly SettingsRegistry $settings) {}

    public function provider(): PaymentProvider
    {
        return PaymentProvider::Stripe;
    }

    public function isConfigured(): bool
    {
        return $this->secretKey() !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function session(Booking $booking, Payment $payment): array
    {
        if ($payment->gateway_ref === null) {
            $response = Http::withToken($this->secretKey())
                ->asForm()
                ->timeout(15)
                ->post(self::API.'/checkout/sessions', [
                    'mode' => 'payment',
                    'line_items[0][price_data][currency]' => strtolower($payment->currency),
                    'line_items[0][price_data][unit_amount]' => $this->minorUnits($payment->amount),
                    'line_items[0][price_data][product_data][name]' => $booking->code,
                    'line_items[0][quantity]' => 1,
                    'metadata[booking_id]' => (string) $booking->id,
                    'success_url' => route('payments.stripe.return', $booking).'?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => route('bookings.pay', $booking),
                ]);

            if ($response->failed()) {
                throw ValidationException::withMessages([
                    'payment' => __('The payment could not be started. Please try again.'),
                ]);
            }

            $payment->forceFill([
                'gateway_ref' => (string) $response->json('id'),
                'payload' => ['session' => $response->json()],
            ])->save();
        }

        return [
            'url' => data_get($payment->payload, 'session.url'),
        ];
    }

    /**
     * Ask Stripe whether a returned session is actually paid (D15: the
     * redirect proves nothing — the API or webhook does).
     */
    public function isSessionPaid(string $sessionId): bool
    {
        $response = Http::withToken($this->secretKey())
            ->timeout(15)
            ->get(self::API.'/checkout/sessions/'.$sessionId);

        return $response->successful() && $response->json('payment_status') === 'paid';
    }

    /**
     * Stripe-Signature: "t=<ts>,v1=<hmac>" over "<ts>.<body>".
     */
    public function verifyWebhookSignature(string $body, string $signature): bool
    {
        $secret = $this->settings->string('payments.stripe_webhook_secret');

        if ($secret === '' || $signature === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signature) as $pair) {
            [$key, $value] = array_pad(explode('=', trim($pair), 2), 2, '');
            $parts[$key][] = $value;
        }

        $timestamp = $parts['t'][0] ?? '';
        $expected = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        foreach ($parts['v1'] ?? [] as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function parseWebhook(array $event): ?WebhookResult
    {
        $type = $event['type'] ?? null;

        if (! in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_failed'], true)) {
            return null;
        }

        $session = data_get($event, 'data.object');
        $sessionId = is_array($session) ? ($session['id'] ?? null) : null;

        // A usable session id proves $session was the array we expected.
        if (! is_string($sessionId) || $sessionId === '') {
            return null;
        }

        $captured = $type === 'checkout.session.completed'
            && data_get($session, 'payment_status') === 'paid';

        return new WebhookResult(
            gatewayRef: $sessionId,
            captured: $captured,
            payload: $session,
        );
    }

    public function publishableKey(): string
    {
        return $this->settings->string('payments.stripe_publishable_key');
    }

    private function secretKey(): string
    {
        return $this->settings->string('payments.stripe_secret_key');
    }

    private function minorUnits(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
