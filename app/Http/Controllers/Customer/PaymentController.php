<?php

namespace App\Http\Controllers\Customer;

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Payments\Actions\ConfirmPayment;
use App\Domain\Payments\Actions\InitiateGatewayPayment;
use App\Domain\Payments\Actions\PayWithWallet;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentState;
use App\Domain\Payments\GatewayManager;
use App\Domain\Payments\Gateways\RazorpayGateway;
use App\Domain\Payments\Gateways\StripeGateway;
use App\Domain\Payments\WalletService;
use App\Domain\Settings\SettingsRegistry;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customer side of online payment (M08): the pay page, gateway session
 * initiation, the Razorpay signature callback and the Stripe return leg.
 * Webhooks live in the top-level WebhookController.
 */
class PaymentController extends Controller
{
    public function show(
        Request $request,
        Booking $booking,
        GatewayManager $gateways,
        WalletService $wallet,
        SettingsRegistry $settings,
    ): Response|RedirectResponse {
        $customer = $this->customerFor($request, $booking);

        if ($booking->status !== BookingStatus::PendingPayment) {
            return redirect()->route('bookings.show', $booking);
        }

        $booking->load('items');
        $balance = $wallet->balance($customer);
        $timeout = $settings->integer('booking.payment_timeout_minutes', 30);

        return Inertia::render('customer/bookings/pay', [
            'booking' => new BookingResource($booking),
            'methods' => [
                'gateways' => array_map(
                    fn ($gateway): string => $gateway->provider()->value,
                    $gateways->configured(),
                ),
                'wallet' => [
                    'enabled' => $settings->boolean('payments.wallet_enabled', true),
                    'balance' => number_format($balance, 2, '.', ''),
                    'sufficient' => $balance >= (float) $booking->total,
                ],
            ],
            'expires_at' => $booking->created_at?->addMinutes($timeout)->toIso8601String(),
        ]);
    }

    /**
     * Create/reuse the gateway session for the chosen provider (JSON — the
     * pay page opens the Razorpay modal or follows the Stripe URL from this).
     */
    public function initiate(
        Request $request,
        Booking $booking,
        string $provider,
        InitiateGatewayPayment $action,
        StripeGateway $stripe,
    ): JsonResponse {
        $this->customerFor($request, $booking);

        $providerEnum = PaymentProvider::tryFrom($provider);

        if ($providerEnum === null || ! $providerEnum->isOnlineGateway()) {
            abort(404);
        }

        $result = $action->handle($booking, $providerEnum);

        $session = $result['session'];

        if ($providerEnum === PaymentProvider::Stripe) {
            $session['publishable_key'] = $stripe->publishableKey();
        }

        return response()->json([
            'provider' => $providerEnum->value,
            'session' => $session,
        ]);
    }

    /**
     * Razorpay checkout.js success handler: cryptographic proof via HMAC —
     * confirm immediately; the webhook replay later is an idempotent no-op.
     */
    public function razorpayCallback(
        Request $request,
        Booking $booking,
        RazorpayGateway $gateway,
        ConfirmPayment $confirm,
    ): JsonResponse {
        $this->customerFor($request, $booking);

        $validated = $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        if (! $gateway->verifyCallbackSignature(
            $validated['razorpay_order_id'],
            $validated['razorpay_payment_id'],
            $validated['razorpay_signature'],
        )) {
            throw ValidationException::withMessages([
                'payment' => __('The payment could not be verified.'),
            ]);
        }

        $payment = $booking->payments()
            ->where('gateway', PaymentProvider::Razorpay->value)
            ->where('gateway_ref', $validated['razorpay_order_id'])
            ->firstOrFail();

        $confirm->handle($payment, ['payment_id' => $validated['razorpay_payment_id']]);

        return response()->json([
            'redirect' => route('bookings.show', $booking),
        ]);
    }

    /**
     * Stripe success return. The redirect proves nothing (D15) — ask the API
     * whether the session is actually paid; the webhook remains the backstop.
     */
    public function stripeReturn(
        Request $request,
        Booking $booking,
        StripeGateway $gateway,
        ConfirmPayment $confirm,
    ): RedirectResponse {
        $this->customerFor($request, $booking);

        $sessionId = (string) $request->query('session_id');

        $payment = $booking->payments()
            ->where('gateway', PaymentProvider::Stripe->value)
            ->where('gateway_ref', $sessionId)
            ->first();

        if ($payment !== null && $payment->status !== PaymentState::Captured && $gateway->isSessionPaid($sessionId)) {
            $confirm->handle($payment);
        }

        $paid = $payment !== null && $payment->refresh()->status === PaymentState::Captured;

        return redirect()
            ->route('bookings.show', $booking)
            ->with($paid ? 'success' : 'error', $paid
                ? __('Payment received! Your booking is confirmed.')
                : __('Your payment is still processing. This page will update once it is confirmed.'));
    }

    public function payWithWallet(Request $request, Booking $booking, PayWithWallet $action): RedirectResponse
    {
        $customer = $this->customerFor($request, $booking);

        $action->handle($booking, $customer);

        return redirect()
            ->route('bookings.show', $booking)
            ->with('success', __('Paid from your wallet. Your booking is confirmed.'));
    }

    private function customerFor(Request $request, Booking $booking): User
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($booking->customer_id === $user->id, 404);

        return $user;
    }
}
