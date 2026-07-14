<?php

namespace App\Domain\Comms\Contracts;

use App\Domain\Comms\SmsResult;

/**
 * An SMS provider (M23). Drivers speak raw HTTP — no vendor SDKs (D15's rule,
 * borrowed from the payment gateways) — and never throw: a dead SMS provider
 * must not fail the booking that triggered the message (M07's rule for a dead
 * Reverb). Failure comes back as an SmsResult and lands in `sms_logs`.
 */
interface SmsGateway
{
    /** Settings value that selects this driver: `sms.gateway`. */
    public function key(): string;

    public function label(): string;

    public function isConfigured(): bool;

    public function send(string $phone, string $body): SmsResult;
}
