<?php

namespace App\Http\Requests\Customer;

use App\Models\Address;

class UpdateAddressRequest extends StoreAddressRequest
{
    public function authorize(): bool
    {
        $address = $this->route('address');

        return $address instanceof Address
            && ($this->user()?->can('update', $address) ?? false);
    }
}
