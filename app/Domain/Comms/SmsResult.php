<?php

namespace App\Domain\Comms;

/**
 * What a gateway did with one message (M23). Straight into `sms_logs`.
 */
class SmsResult
{
    /**
     * @param  array<string, mixed>  $response
     */
    public function __construct(
        public readonly bool $sent,
        public readonly array $response = [],
        public readonly ?string $error = null,
    ) {}

    /**
     * @param  array<string, mixed>  $response
     */
    public static function sent(array $response = []): self
    {
        return new self(true, $response);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public static function failed(string $error, array $response = []): self
    {
        return new self(false, $response, $error);
    }
}
