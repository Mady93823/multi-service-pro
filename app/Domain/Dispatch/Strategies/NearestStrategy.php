<?php

namespace App\Domain\Dispatch\Strategies;

use App\Domain\Dispatch\Contracts\DispatchStrategy;
use App\Domain\Dispatch\Enums\DispatchMode;
use Illuminate\Support\Collection;

/**
 * Offer the single closest provider; if they decline or the offer times out,
 * the next round picks the next closest (the finder excludes prior offers).
 */
class NearestStrategy implements DispatchStrategy
{
    public function mode(): DispatchMode
    {
        return DispatchMode::Nearest;
    }

    public function pick(Collection $eligible): Collection
    {
        return $eligible->take(1)->values();
    }
}
