<?php

namespace App\Domain\Bookings\Actions;

use App\Domain\Bookings\CartManager;
use App\Models\Booking;

class RebookBooking
{
    public function __construct(private readonly CartManager $cart) {}

    /**
     * "Book again" (M04, UC parity): refill the cart from a past booking's
     * item snapshots. Services or add-ons that have since been retired are
     * skipped — CartManager::detailed() only resolves live catalog rows.
     * Returns how many lines were added.
     */
    public function handle(Booking $booking): int
    {
        $before = $this->cart->count();

        foreach ($booking->items as $item) {
            $addonIds = array_map(
                fn (array $addon): int => (int) $addon['id'],
                $item->addons_snapshot ?? [],
            );

            $this->cart->add($item->service_id, $item->qty, $addonIds);
        }

        // Force stale-line cleanup so the count reflects bookable lines only.
        $this->cart->detailed();

        return max(0, $this->cart->count() - $before);
    }
}
