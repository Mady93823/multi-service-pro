<?php

namespace App\Http\Controllers;

use App\Domain\Payments\Actions\ConfirmPayment;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Enums\PaymentState;
use App\Domain\Payments\Gateways\PayPalGateway;
use App\Domain\Payments\Gateways\PayUGateway;
use App\Domain\Payments\Gateways\RazorpayGateway;
use App\Domain\Payments\Gateways\StripeGateway;
use App\Domain\Payments\WebhookResult;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Gateway webhooks (M08) — the source of truth for online money. Signature-
 * verified against the raw body, idempotent (ConfirmPayment no-ops replays),
 * and always 200 on events we simply do not act on, so gateways stop
 * retrying them.
 */
class WebhookController extends Controller
{
    public function razorpay(Request $request, RazorpayGateway $gateway, ConfirmPayment $confirm): JsonResponse
    {
        return $this->process(
            $request,
            $gateway,
            $confirm,
            (string) $request->header('X-Razorpay-Signature', ''),
        );
    }

    public function stripe(Request $request, StripeGateway $gateway, ConfirmPayment $confirm): JsonResponse
    {
        return $this->process(
            $request,
            $gateway,
            $confirm,
            (string) $request->header('Stripe-Signature', ''),
        );
    }

    /**
     * PayU posts form-encoded fields, not JSON, and its proof is the reverse
     * hash inside those fields rather than a signature header (D39).
     */
    public function payu(Request $request, PayUGateway $gateway, ConfirmPayment $confirm): JsonResponse
    {
        $body = $request->getContent();

        if (! $gateway->verifyWebhookSignature($body, '')) {
            abort(400, 'Invalid webhook signature.');
        }

        // Decode the raw body rather than reading $request->post(): the fields
        // must be exactly the ones the hash was verified over.
        return $this->settle($gateway, $confirm, $gateway->parseWebhook($gateway->fieldsFromBody($body)));
    }

    /**
     * PayPal's authenticity check is an API call, so the transmission headers
     * travel to the gateway packed as the contract's signature string (D39).
     */
    public function paypal(Request $request, PayPalGateway $gateway, ConfirmPayment $confirm): JsonResponse
    {
        $signature = (string) json_encode([
            'transmission_id' => $request->header('Paypal-Transmission-Id', ''),
            'transmission_time' => $request->header('Paypal-Transmission-Time', ''),
            'transmission_sig' => $request->header('Paypal-Transmission-Sig', ''),
            'cert_url' => $request->header('Paypal-Cert-Url', ''),
            'auth_algo' => $request->header('Paypal-Auth-Algo', ''),
        ]);

        if (! $gateway->verifyWebhookSignature($request->getContent(), $signature)) {
            abort(400, 'Invalid webhook signature.');
        }

        /** @var array<string, mixed> $event */
        $event = $request->json()->all();

        // An approved order is money the customer has agreed to but we have
        // not taken — the closed-browser case the return leg never sees.
        // Capturing here is what actually moves it (D39).
        if (($event['event_type'] ?? null) === 'CHECKOUT.ORDER.APPROVED') {
            $orderId = data_get($event, 'resource.id');

            if (is_string($orderId) && $orderId !== '') {
                $payment = Payment::query()
                    ->where('gateway', $gateway->provider()->value)
                    ->where('gateway_ref', $orderId)
                    ->first();

                if ($payment !== null && $payment->status === PaymentState::Initiated && $gateway->captureOrder($orderId)) {
                    $confirm->handle($payment);
                }
            }

            return response()->json(['received' => true]);
        }

        return $this->settle($gateway, $confirm, $gateway->parseWebhook($event));
    }

    private function process(Request $request, PaymentGateway $gateway, ConfirmPayment $confirm, string $signature): JsonResponse
    {
        $body = $request->getContent();

        if (! $gateway->verifyWebhookSignature($body, $signature)) {
            abort(400, 'Invalid webhook signature.');
        }

        /** @var array<string, mixed> $event */
        $event = $request->json()->all();

        return $this->settle($gateway, $confirm, $gateway->parseWebhook($event));
    }

    /**
     * Shared tail of every webhook: find the payment the (already verified)
     * event refers to and confirm or fail it — idempotently either way.
     */
    private function settle(PaymentGateway $gateway, ConfirmPayment $confirm, ?WebhookResult $result): JsonResponse
    {
        if ($result === null) {
            return response()->json(['received' => true]);
        }

        $payment = Payment::query()
            ->where('gateway', $gateway->provider()->value)
            ->where('gateway_ref', $result->gatewayRef)
            ->first();

        if ($payment === null) {
            Log::info('Webhook for an unknown payment reference ignored.', [
                'gateway' => $gateway->provider()->value,
                'ref' => $result->gatewayRef,
            ]);

            return response()->json(['received' => true]);
        }

        if ($result->captured) {
            $confirm->handle($payment, $result->payload);
        } elseif ($payment->status === PaymentState::Initiated) {
            $payment->forceFill([
                'status' => PaymentState::Failed,
                'payload' => array_merge($payment->payload ?? [], ['failure' => $result->payload]),
            ])->save();
        }

        return response()->json(['received' => true]);
    }
}
