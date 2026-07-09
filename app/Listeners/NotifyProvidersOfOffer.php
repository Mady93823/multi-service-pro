<?php

namespace App\Listeners;

use App\Domain\Dispatch\Events\BookingOffered;
use App\Models\DispatchOffer;
use App\Models\User;
use App\Notifications\NewJobOfferNotification;

/**
 * Push a live "new job offer" to every provider a dispatch round reached
 * (M06 → M11). Providers who are offline for realtime still see it in-app.
 */
class NotifyProvidersOfOffer
{
    public function handle(BookingOffered $event): void
    {
        $providerIds = $event->offers
            ->map(fn (DispatchOffer $offer): int => $offer->provider_id)
            ->unique()
            ->all();

        User::query()->whereIn('id', $providerIds)->get()
            ->each(fn (User $provider) => $provider->notify(new NewJobOfferNotification($event->booking)));
    }
}
