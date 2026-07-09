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
 * Razorpay via its REST API (M08, default gateway — D7). Order is created
 * server-side; the pay page opens checkout.js with that order; the handler
 * posts back order/payment/signature which we verify with HMAC; the
 * payment.captured webhook is the safety net for closed browsers.
 */
class RazorpayGateway implements PaymentGateway
{
    private const API = 'https://api.razorpay.com/v1';

    public function __construct(private readonly SettingsRegistry $settings) {}

    public function provider(): PaymentProvider
    {
        return PaymentProvider::Razorpay;
    }

    public function isConfigured(): bool
    {
        return $this->keyId() !== '' && $this->keySecret() !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function session(Booking $booking, Payment $payment): array
    {
        if ($payment->gateway_ref === null) {
            $response = Http::withBasicAuth($this->keyId(), $this->keySecret())
                ->timeout(15)
                ->post(self::API.'/orders', [
                    'amount' => $this->paise($payment->amount),
                    'currency' => $payment->currency,
                    'receipt' => $booking->code,
                    'notes' => ['booking_id' => (string) $booking->id],
                ]);

            if ($response->failed()) {
                throw ValidationException::withMessages([
                    'payment' => __('The payment could not be started. Please try again.'),
                ]);
            }

            $payment->forceFill([
                'gateway_ref' => (string) $response->json('id'),
                'payload' => ['order' => $response->json()],
            ])->save();
        }

        return [
            'key_id' => $this->keyId(),
            'order_id' => $payment->gateway_ref,
            'amount' => $this->paise($payment->amount),
            'currency' => $payment->currency,
        ];
    }

    /**
     * The checkout.js success handler proof: HMAC-SHA256("order|payment").
     */
    public function verifyCallbackSignature(string $orderId, string $paymentId, string $signature): bool
    {
        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, $this->keySecret());

        return hash_equals($expected, $signature);
    }

    public function verifyWebhookSignature(string $body, string $signature): bool
    {
        $secret = $this->settings->string('payments.razorpay_webhook_secret');

        if ($secret === '' || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $body, $secret), $signature);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function parseWebhook(array $event): ?WebhookResult
    {
        $type = $event['event'] ?? null;

        if (! in_array($type, ['payment.captured', 'payment.failed'], true)) {
            return null;
        }

        $entity = data_get($event, 'payload.payment.entity');
        $orderId = is_array($entity) ? ($entity['order_id'] ?? null) : null;

        // A usable order id proves $entity was the array we expected.
        if (! is_string($orderId) || $orderId === '') {
            return null;
        }

        return new WebhookResult(
            gatewayRef: $orderId,
            captured: $type === 'payment.captured',
            payload: $entity,
        );
    }

    private function keyId(): string
    {
        return $this->settings->string('payments.razorpay_key_id');
    }

    private function keySecret(): string
    {
        return $this->settings->string('payments.razorpay_key_secret');
    }

    private function paise(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
