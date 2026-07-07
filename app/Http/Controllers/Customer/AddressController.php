<?php

namespace App\Http\Controllers\Customer;

use App\Domain\Addresses\Actions\DeleteAddress;
use App\Domain\Addresses\Actions\SaveAddress;
use App\Domain\Addresses\Actions\SetDefaultAddress;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreAddressRequest;
use App\Http\Requests\Customer\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AddressController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $addresses = $user
            ->addresses()
            ->with('zone')
            ->orderByDesc('is_default')
            ->latest('id')
            ->get();

        return Inertia::render('customer/addresses/index', [
            'addresses' => AddressResource::collection($addresses),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('customer/addresses/create');
    }

    public function store(StoreAddressRequest $request, SaveAddress $action): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $address = $action->handle($user, $request->validated());

        return to_route('addresses.index')->with(
            'success',
            $address->zone_id !== null
                ? __('Address saved.')
                : __('Address saved, but it is outside our current service area.')
        );
    }

    public function edit(Address $address): Response
    {
        Gate::authorize('update', $address);

        $address->load('zone');

        return Inertia::render('customer/addresses/edit', [
            'address' => new AddressResource($address),
        ]);
    }

    public function update(UpdateAddressRequest $request, Address $address, SaveAddress $action): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $address = $action->handle($user, $request->validated(), $address);

        return to_route('addresses.index')->with(
            'success',
            $address->zone_id !== null
                ? __('Address updated.')
                : __('Address saved, but it is outside our current service area.')
        );
    }

    public function destroy(Request $request, Address $address, DeleteAddress $action): RedirectResponse
    {
        Gate::authorize('delete', $address);

        $action->handle($address);

        return to_route('addresses.index')->with('success', __('Address deleted.'));
    }

    public function setDefault(Request $request, Address $address, SetDefaultAddress $action): RedirectResponse
    {
        Gate::authorize('update', $address);

        $action->handle($address);

        return to_route('addresses.index')->with('success', __('Default address updated.'));
    }
}
