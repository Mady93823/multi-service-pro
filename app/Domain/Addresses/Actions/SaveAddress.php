<?php

namespace App\Domain\Addresses\Actions;

use App\Domain\Zones\ZoneResolver;
use App\Models\Address;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveAddress
{
    public function __construct(private ZoneResolver $zones) {}

    /**
     * Creates or updates an address, resolving its zone from the pin.
     * The first address becomes the default automatically; is_default
     * can only be granted here, never revoked (use another address).
     *
     * @param  array{label: string, line1: string, line2?: string|null, city: string, postal_code: string, lat: float|string, lng: float|string, is_default?: bool}  $data
     */
    public function handle(User $user, array $data, ?Address $address = null): Address
    {
        $makeDefault = (bool) ($data['is_default'] ?? false);
        unset($data['is_default']);

        $data['zone_id'] = $this->zones->resolve((float) $data['lat'], (float) $data['lng'])?->id;

        return DB::transaction(function () use ($user, $data, $address, $makeDefault) {
            if ($address === null) {
                $address = $user->addresses()->create(
                    $data + ['is_default' => ! $user->addresses()->exists()]
                );
            } else {
                $address->update($data);
            }

            if ($makeDefault && ! $address->is_default) {
                $user->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
                $address->update(['is_default' => true]);
            }

            return $address;
        });
    }
}
