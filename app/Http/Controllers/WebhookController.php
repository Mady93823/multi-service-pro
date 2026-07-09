<?php

namespace App\Http\Controllers;

use App\Domain\Payments\Actions\ConfirmPayment;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Enums\PaymentState;
use App\Domain\Payments\Gateways\RazorpayGateway;
use App\Domain\Payments\Gateways\StripeGateway;
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

    private function process(Request $request, PaymentGateway $gateway, ConfirmPayment $confirm, string $signature): JsonResponse
    {
        $body = $request->getContent();

        if (! $gateway->verifyWebhookSignature($body, $signature)) {
            abort(400, 'Invalid webhook signature.');
        }

        /** @var array<string, mixed> $event */
        $event = $request->json()->all();

        $result = $gateway->parseWebhook($event);

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
