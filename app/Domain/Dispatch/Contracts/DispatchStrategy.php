<?php

namespace App\Domain\Dispatch\Contracts;

use App\Domain\Dispatch\EligibleProvider;
use App\Domain\Dispatch\Enums\DispatchMode;
use Illuminate\Support\Collection;

/**
 * Picks which of the eligible providers get an offer this round (M06). The
 * eligibility work is shared (EligibleProviders); a strategy only decides how
 * many and in what order — nearest offers one at a time, broadcast offers all.
 */
interface DispatchStrategy
{
    public function mode(): DispatchMode;

    /**
     * @param  Collection<int, EligibleProvider>  $eligible
     * @return Collection<int, EligibleProvider>
     */
    public function pick(Collection $eligible): Collection;
}
