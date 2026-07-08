<?php

namespace App\Domain\Bookings\Actions;

use App\Models\User;

class ToggleFavoriteProvider
{
    /**
     * Favorite/unfavorite a provider (M04). Dispatch tries favorites first
     * when the M06 setting is on. Returns true when now favorited.
     */
    public function handle(User $customer, User $provider): bool
    {
        if ($customer->hasFavorited($provider)) {
            $customer->favoriteProviders()->detach($provider->id);

            return false;
        }

        $customer->favoriteProviders()->attach($provider->id);

        return true;
    }
}
