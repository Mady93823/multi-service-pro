<?php

namespace App\Http\Controllers\Customer;

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Payments\Actions\ConfirmPayment;
use App\Domain\Payments\Actions\InitiateGatewayPayment;
use App\Domain\Payments\Actions\PayWithWallet;
use App\Domain\Payments\Actions\SubmitOfflinePayment;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentState;
use App\Domain\Payments\GatewayManager;
use App\Domain\Payments\Gateways\PayPalGateway;
use App\Domain\Payments\Gateways\PayUGateway;
use App\Domain\Payments\Gateways\RazorpayGateway;
use App\Domain\Payments\Gateways\StripeGateway;
use App\Domain\Payments\WalletService;
use App\Domain\Settings\SettingsRegistry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\SubmitOfflinePaymentRequest;
use App\Http\Resources\BankAccountResource;
use App\Http\Resources\BookingResource;
use App\Models\BankAccount;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customer side of online payment (M08, gateways extended D39): the pay page,
 * gateway session initiation, and each gateway's browser leg — Razorpay's
 * signature callback, Stripe's return, PayU's cross-site POST return and
 * PayPal's capture return. Webhooks live in the top-level WebhookController.
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

        $offlineEnabled = $settings->boolean('payments.offline_enabled', false);

        $accounts = $offlineEnabled
            ? BankAccount::query()->active()->with('media')->orderBy('sort_order')->orderBy('id')->get()
            : collect();

        // An offline declaration already waiting for an admin (M22): the page
        // shows "we are checking your transfer" instead of the form again.
        /** @var Payment|null $pending */
        $pending = $booking->payments()->awaitingVerification()->latest('id')->first();

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
                'offline' => [
                    'enabled' => $offlineEnabled && $accounts->isNotEmpty(),
                    'instructions' => $settings->string('payments.offline_instructions'),
                    'accounts' => BankAccountResource::collection($accounts)->resolve(),
                ],
            ],
            'pending_offline' => $pending === null ? null : [
                'id' => $pending->id,
                'reference' => $pending->reference,
                'submitted_at' => $pending->updated_at?->toIso8601String(),
            ],
            'expires_at' => $booking->created_at?->addMinutes($timeout)->toIso8601String(),
        ]);
    }

    /**
     * "I have transferred the money." Creates the offline payments row and
     * stores the proof; an admin still has to verify it before the booking
     * moves (D27) — nothing here settles anything.
     */
    public function offline(SubmitOfflinePaymentRequest $request, Booking $booking, SubmitOfflinePayment $action): RedirectResponse
    {
        $this->customerFor($request, $booking);

        /** @var BankAccount $account */
        $account = BankAccount::query()->findOrFail((int) $request->validated('bank_account_id'));

        $proof = $request->file('proof');

        $action->handle(
            $booking,
            $account,
            $request->validated('reference'),
            $proof instanceof UploadedFile ? $proof : null,
        );

        return back()->with('success', __('Thanks — we will confirm your transfer shortly.'));
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

    /**
     * PayU posts the customer back cross-site, so the session cookie does not
     * ride (SameSite=Lax) and this route is unauthenticated + CSRF-exempt.
     * Trust comes from the reverse hash over the merchant salt — webhook-grade
     * proof — and a success still re-asks the verify API before settling
     * (D39). The redirect out is a top-level GET, which carries the cookie
     * again, so the customer lands signed in on their booking.
     */
    public function payuReturn(Request $request, PayUGateway $gateway, ConfirmPayment $confirm): RedirectResponse
    {
        /** @var array<string, mixed> $fields */
        $fields = $request->post();

        $txnid = (string) ($fields['txnid'] ?? '');

        /** @var Payment|null $payment */
        $payment = Payment::query()
            ->where('gateway', PaymentProvider::PayU->value)
            ->where('gateway_ref', $txnid)
            ->first();

        if ($payment === null) {
            abort(404);
        }

        abort_unless($gateway->verifyResponseHash($fields), 400, 'Invalid PayU response hash.');

        $status = (string) ($fields['status'] ?? '');

        if ($status === 'success' && $payment->status !== PaymentState::Captured && $gateway->isPaymentVerified($txnid)) {
            $confirm->handle($payment, ['payu' => $fields]);
        } elseif ($status === 'failure' && $payment->status === PaymentState::Initiated) {
            $payment->forceFill([
                'status' => PaymentState::Failed,
                'payload' => array_merge($payment->payload ?? [], ['failure' => $fields]),
            ])->save();
        }

        $paid = $payment->refresh()->status === PaymentState::Captured;

        return redirect()
            ->route('bookings.show', $payment->booking_id)
            ->with($paid ? 'success' : 'error', $paid
                ? __('Payment received! Your booking is confirmed.')
                : __('The payment did not go through. You can try again from the payment page.'));
    }

    /**
     * PayPal approve return. Approval is not money — capturing the order is,
     * and the capture API response is the confirmation (D39; Stripe's
     * isSessionPaid idiom). The webhook capture remains the backstop.
     */
    public function paypalReturn(
        Request $request,
        Booking $booking,
        PayPalGateway $gateway,
        ConfirmPayment $confirm,
    ): RedirectResponse {
        $this->customerFor($request, $booking);

        $orderId = (string) $request->query('token');

        $payment = $booking->payments()
            ->where('gateway', PaymentProvider::PayPal->value)
            ->where('gateway_ref', $orderId)
            ->first();

        if ($payment !== null && $payment->status !== PaymentState::Captured && $gateway->captureOrder($orderId)) {
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
