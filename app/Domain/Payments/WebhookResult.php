<?php

namespace App\Domain\Payments;

/**
 * A webhook event reduced to what the platform acts on: which payment
 * (by gateway reference) and whether the money was captured.
 */
final readonly class WebhookResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $gatewayRef,
        public bool $captured,
        public array $payload = [],
    ) {}
}
