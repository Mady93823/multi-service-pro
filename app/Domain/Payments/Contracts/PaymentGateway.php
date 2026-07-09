<?php

namespace App\Domain\Payments\Contracts;

use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\WebhookResult;
use App\Models\Booking;
use App\Models\Payment;

/**
 * Online payment gateway seam (M08). Implementations talk to the gateway with
 * the plain Laravel HTTP client (ADR D15 — no vendor SDKs) and read their
 * credentials from admin-editable settings, so an install can enable a
 * gateway without touching .env.
 */
interface PaymentGateway
{
    public function provider(): PaymentProvider;

    /** Credentials present? An unconfigured gateway is hidden everywhere. */
    public function isConfigured(): bool;

    /**
     * Ensure an upstream order/session exists for this payment (creating one
     * on first call and reusing it after), then return whatever the pay page
     * needs to hand the customer over to the gateway.
     *
     * @return array<string, mixed>
     */
    public function session(Booking $booking, Payment $payment): array;

    /** Constant-time check of a webhook body against its signature header. */
    public function verifyWebhookSignature(string $body, string $signature): bool;

    /**
     * Translate a webhook event into a capture/failure against a payment
     * reference; null = an event type this integration ignores.
     *
     * @param  array<string, mixed>  $event
     */
    public function parseWebhook(array $event): ?WebhookResult;
}
