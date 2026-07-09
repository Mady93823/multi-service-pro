<?php

namespace App\Domain\Tracking;

use Carbon\CarbonImmutable;

/**
 * A validated provider GPS reading on its way into RecordTrackingPing.
 */
final readonly class GeoPing
{
    public function __construct(
        public float $lat,
        public float $lng,
        public ?float $accuracy = null,
        public ?float $heading = null,
        public ?float $speed = null,
        public ?CarbonImmutable $recordedAt = null,
    ) {}

    public function recordedAt(): CarbonImmutable
    {
        return $this->recordedAt ?? CarbonImmutable::now();
    }
}
