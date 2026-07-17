<?php

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\WebhookResult;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * PayU India via its classic hosted checkout (D39). No upstream order exists:
 * the pay page POSTs a signed form straight to PayU, and PayU POSTs the result
 * back to surl/furl cross-site. Every leg is proven by a sha512 hash over the
 * salt — request hash going out, *reverse* hash coming back — and a success is
 * additionally re-asked from the verify_payment API before it settles
 * anything, because a browser redirect proves nothing (D15).
 */
class PayUGateway implements PaymentGateway
{
    public function __construct(private readonly SettingsRegistry $settings) {}

    public function provider(): PaymentProvider
    {
        return PaymentProvider::PayU;
    }

    public function isConfigured(): bool
    {
        return $this->key() !== '' && $this->salt() !== '';
    }

    /**
     * The signed form the pay page auto-submits. The txnid is minted once and
     * stored as gateway_ref, so a page refresh re-signs the same transaction
     * instead of starting a parallel one.
     *
     * @return array<string, mixed>
     */
    public function session(Booking $booking, Payment $payment): array
    {
        // Explicit, not lazy: the route-bound booking arrives bare and P7.2's
        // preventLazyLoading treats an implicit load as a failure.
        $booking->loadMissing('customer');

        if ($payment->gateway_ref === null) {
            $payment->forceFill([
                'gateway_ref' => 'PU'.$payment->id.Str::upper(Str::random(10)),
            ])->save();
        }

        $fields = [
            'key' => $this->key(),
            'txnid' => (string) $payment->gateway_ref,
            'amount' => $payment->amount,
            'productinfo' => $booking->code,
            'firstname' => (string) $booking->customer?->name,
            'email' => (string) $booking->customer?->email,
            'phone' => (string) $booking->customer?->phone,
            'udf1' => (string) $booking->id,
            'surl' => route('payments.payu.return'),
            'furl' => route('payments.payu.return'),
        ];

        $fields['hash'] = $this->requestHash($fields);

        return [
            'action' => $this->baseUrl().'/_payment',
            'fields' => $fields,
        ];
    }

    /**
     * The reverse hash PayU sends with every response (return leg and
     * webhook): sha512 of the fields in mirrored order, salt first.
     *
     * @param  array<string, mixed>  $fields
     */
    public function verifyResponseHash(array $fields): bool
    {
        $received = (string) ($fields['hash'] ?? '');

        if ($received === '' || $this->salt() === '') {
            return false;
        }

        $pick = fn (string $key): string => (string) ($fields[$key] ?? '');

        $expected = hash('sha512', implode('|', [
            $this->salt(),
            $pick('status'),
            '', '', '', '', '',
            $pick('udf5'), $pick('udf4'), $pick('udf3'), $pick('udf2'), $pick('udf1'),
            $pick('email'), $pick('firstname'), $pick('productinfo'),
            $pick('amount'), $pick('txnid'), $this->key(),
        ]));

        return hash_equals($expected, strtolower($received));
    }

    /**
     * Ask PayU's verify_payment API whether a transaction really succeeded —
     * the return leg's hash proves the message came from PayU, this proves the
     * money actually moved (D15's rule, PayU edition).
     */
    public function isPaymentVerified(string $txnid): bool
    {
        $command = 'verify_payment';

        $response = Http::asForm()
            ->timeout(15)
            ->post($this->verifyUrl().'/merchant/postservice.php?form=2', [
                'key' => $this->key(),
                'command' => $command,
                'var1' => $txnid,
                'hash' => hash('sha512', implode('|', [$this->key(), $command, $txnid, $this->salt()])),
            ]);

        return $response->successful()
            && (int) $response->json('status') === 1
            && $response->json('transaction_details.'.$txnid.'.status') === 'success';
    }

    /**
     * PayU webhooks are form-encoded, and the proof is the reverse hash inside
     * the fields — there is no signature header, so $signature is unused.
     */
    public function verifyWebhookSignature(string $body, string $signature): bool
    {
        return $this->verifyResponseHash($this->fieldsFromBody($body));
    }

    /**
     * Decode PayU's form-encoded body into the field array its hash covers.
     *
     * @return array<string, mixed>
     */
    public function fieldsFromBody(string $body): array
    {
        parse_str($body, $parsed);

        $fields = [];

        foreach ($parsed as $key => $value) {
            $fields[(string) $key] = $value;
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function parseWebhook(array $event): ?WebhookResult
    {
        $status = $event['status'] ?? null;

        if (! in_array($status, ['success', 'failure'], true)) {
            return null;
        }

        $txnid = $event['txnid'] ?? null;

        if (! is_string($txnid) || $txnid === '') {
            return null;
        }

        return new WebhookResult(
            gatewayRef: $txnid,
            captured: $status === 'success',
            payload: $event,
        );
    }

    private function baseUrl(): string
    {
        return $this->live() ? 'https://secure.payu.in' : 'https://test.payu.in';
    }

    /** The verify API lives on a different host in production than checkout. */
    private function verifyUrl(): string
    {
        return $this->live() ? 'https://info.payu.in' : 'https://test.payu.in';
    }

    private function live(): bool
    {
        return $this->settings->string('payments.payu_mode', 'test') === 'live';
    }

    private function key(): string
    {
        return $this->settings->string('payments.payu_key');
    }

    private function salt(): string
    {
        return $this->settings->string('payments.payu_salt');
    }

    /**
     * Request hash: key|txnid|amount|productinfo|firstname|email|udf1..udf5,
     * five reserved empties, then the salt.
     *
     * @param  array<string, string>  $fields
     */
    private function requestHash(array $fields): string
    {
        return hash('sha512', implode('|', [
            $fields['key'], $fields['txnid'], $fields['amount'], $fields['productinfo'],
            $fields['firstname'], $fields['email'],
            $fields['udf1'], '', '', '', '',
            '', '', '', '', '',
            $this->salt(),
        ]));
    }
}
