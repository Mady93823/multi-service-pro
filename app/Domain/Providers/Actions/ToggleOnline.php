<?php

namespace App\Domain\Providers\Actions;

use App\Models\ProviderProfile;
use Illuminate\Validation\ValidationException;

class ToggleOnline
{
    /**
     * Flip the provider's availability. Dispatch (M06) only offers
     * jobs to online providers, so this must be instant.
     */
    public function handle(ProviderProfile $profile): bool
    {
        if (! $profile->isApproved()) {
            throw ValidationException::withMessages([
                'online' => __('Your profile must be approved before you can go online.'),
            ]);
        }

        $profile->is_online = ! $profile->is_online;
        $profile->save();

        return $profile->is_online;
    }
}
