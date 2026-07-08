<?php

namespace App\Domain\Dispatch\Events;

use App\Models\Booking;
use App\Models\DispatchOffer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Collection;

/**
 * Fired when a dispatch round sends offers. M07/M11 push these to providers
 * (FCM + in-app realtime); for now the provider panel reads them on load.
 */
class BookingOffered
{
    use Dispatchable;

    /**
     * @param  Collection<int, DispatchOffer>  $offers
     */
    public function __construct(
        public readonly Booking $booking,
        public readonly Collection $offers,
    ) {}
}
