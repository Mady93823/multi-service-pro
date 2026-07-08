<?php

namespace App\Domain\Dispatch;

use App\Models\ProviderProfile;

/**
 * A provider that passed every dispatch filter, paired with their Haversine
 * distance (km) from the booking address. Strategies rank/pick these.
 */
final readonly class EligibleProvider
{
    public function __construct(
        public ProviderProfile $profile,
        public float $distanceKm,
    ) {}

    public function providerId(): int
    {
        return (int) $this->profile->user_id;
    }
}
