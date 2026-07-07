<?php

namespace App\Domain\Addresses\Actions;

use App\Models\Address;
use Illuminate\Support\Facades\DB;

class DeleteAddress
{
    /**
     * Deletes an address; when it was the default, the most recent
     * remaining address is promoted so the user always has a default.
     */
    public function handle(Address $address): void
    {
        DB::transaction(function () use ($address) {
            $wasDefault = $address->is_default;
            $userId = $address->user_id;

            $address->delete();

            if ($wasDefault) {
                Address::query()
                    ->where('user_id', $userId)
                    ->latest('id')
                    ->first()
                    ?->update(['is_default' => true]);
            }
        });
    }
}
