<?php

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\WebhookResult;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * PayPal Checkout via the Orders v2 REST API (D39). We create an order and
 * redirect to the approve link; money moves only when WE capture the order —
 * the return leg and the CHECKOUT.ORDER.APPROVED webhook both funnel into
 * captureOrder(), whose API response is the confirmation (D15: never the
 * redirect). Webhook authenticity is an API question too — PayPal publishes
 * no local HMAC, so verifyWebhookSignature() asks their verify endpoint.
 *
 * Not offered on INR installs in practice: PayPal cannot settle INR
 * domestically. It exists for international (D8 CodeCanyon) buyers.
 */
class PayPalGateway implements PaymentGateway
{
    public function __construct(private readonly SettingsRegistry $settings) {}

    public function provider(): PaymentProvider
    {
        return PaymentProvider::PayPal;
    }

    public function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function session(Booking $booking, Payment $payment): array
    {
        if ($payment->gateway_ref === null) {
            $response = Http::withToken($this->accessToken())
                ->timeout(15)
                ->post($this->baseUrl().'/v2/checkout/orders', [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [[
                        'amount' => [
                            'currency_code' => $payment->currency,
                            'value' => $payment->amount,
                        ],
                        'custom_id' => (string) $booking->id,
                        'invoice_id' => $booking->code.'-'.$payment->id,
                    ]],
                    'application_context' => [
                        'shipping_preference' => 'NO_SHIPPING',
                        'user_action' => 'PAY_NOW',
                        'return_url' => route('payments.paypal.return', $booking),
                        'cancel_url' => route('bookings.pay', $booking),
                    ],
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
            'url' => $this->approveLink($payment),
        ];
    }

    /**
     * Capture the approved order — the one call that actually moves money.
     * True only when PayPal answers COMPLETED (directly, or via the
     * already-captured error a webhook/return race produces).
     */
    public function captureOrder(string $orderId): bool
    {
        $response = Http::withToken($this->accessToken())
            ->timeout(15)
            ->withBody('{}', 'application/json')
            ->post($this->baseUrl().'/v2/checkout/orders/'.$orderId.'/capture');

        if ($response->successful()) {
            return $response->json('status') === 'COMPLETED';
        }

        // The other leg won the race and captured first. Confirm against the
        // order itself rather than trusting the error string alone.
        if ($response->json('details.0.issue') === 'ORDER_ALREADY_CAPTURED') {
            $order = Http::withToken($this->accessToken())
                ->timeout(15)
                ->get($this->baseUrl().'/v2/checkout/orders/'.$orderId);

            return $order->successful() && $order->json('status') === 'COMPLETED';
        }

        return false;
    }

    /**
     * PayPal has no local signature scheme: authenticity is confirmed by
     * their verify API. $signature carries the transmission headers as JSON
     * (the WebhookController packs them); a missing webhook id fails closed.
     */
    public function verifyWebhookSignature(string $body, string $signature): bool
    {
        $webhookId = $this->settings->string('payments.paypal_webhook_id');
        $headers = json_decode($signature, true);
        $event = json_decode($body, true);

        if ($webhookId === '' || ! is_array($headers) || ! is_array($event)) {
            return false;
        }

        $response = Http::withToken($this->accessToken())
            ->timeout(15)
            ->post($this->baseUrl().'/v1/notifications/verify-webhook-signature', [
                'transmission_id' => (string) ($headers['transmission_id'] ?? ''),
                'transmission_time' => (string) ($headers['transmission_time'] ?? ''),
                'transmission_sig' => (string) ($headers['transmission_sig'] ?? ''),
                'cert_url' => (string) ($headers['cert_url'] ?? ''),
                'auth_algo' => (string) ($headers['auth_algo'] ?? ''),
                'webhook_id' => $webhookId,
                'webhook_event' => $event,
            ]);

        return $response->successful() && $response->json('verification_status') === 'SUCCESS';
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function parseWebhook(array $event): ?WebhookResult
    {
        $type = $event['event_type'] ?? null;

        if (! in_array($type, ['PAYMENT.CAPTURE.COMPLETED', 'PAYMENT.CAPTURE.DENIED'], true)) {
            return null;
        }

        $orderId = data_get($event, 'resource.supplementary_data.related_ids.order_id');

        if (! is_string($orderId) || $orderId === '') {
            return null;
        }

        $resource = data_get($event, 'resource');

        return new WebhookResult(
            gatewayRef: $orderId,
            captured: $type === 'PAYMENT.CAPTURE.COMPLETED',
            payload: is_array($resource) ? $resource : [],
        );
    }

    private function accessToken(): string
    {
        $key = 'paypal:token:'.md5($this->clientId().'|'.$this->mode());

        return (string) Cache::remember($key, now()->addMinutes(50), function (): string {
            $response = Http::withBasicAuth($this->clientId(), $this->clientSecret())
                ->asForm()
                ->timeout(15)
                ->post($this->baseUrl().'/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ]);

            if ($response->failed()) {
                throw ValidationException::withMessages([
                    'payment' => __('The payment could not be started. Please try again.'),
                ]);
            }

            return (string) $response->json('access_token');
        });
    }

    private function approveLink(Payment $payment): ?string
    {
        $links = data_get($payment->payload, 'order.links', []);

        foreach (is_array($links) ? $links : [] as $link) {
            if (is_array($link) && ($link['rel'] ?? null) === 'approve') {
                return $link['href'] ?? null;
            }
        }

        return null;
    }

    private function baseUrl(): string
    {
        return $this->mode() === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function mode(): string
    {
        return $this->settings->string('payments.paypal_mode', 'sandbox');
    }

    private function clientId(): string
    {
        return $this->settings->string('payments.paypal_client_id');
    }

    private function clientSecret(): string
    {
        return $this->settings->string('payments.paypal_client_secret');
    }
}
