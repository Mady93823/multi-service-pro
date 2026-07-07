<?php

namespace App\Domain\Addresses\Actions;

use App\Models\Address;
use Illuminate\Support\Facades\DB;

class SetDefaultAddress
{
    public function handle(Address $address): void
    {
        DB::transaction(function () use ($address) {
            Address::query()
                ->where('user_id', $address->user_id)
                ->whereKeyNot($address->id)
                ->update(['is_default' => false]);

            $address->update(['is_default' => true]);
        });
    }
}
