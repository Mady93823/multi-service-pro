<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Cities\Actions\DeleteCity;
use App\Domain\Cities\Actions\SaveCity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCityRequest;
use App\Http\Requests\Admin\UpdateCityRequest;
use App\Http\Resources\CityResource;
use App\Models\City;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CityController extends Controller
{
    public function index(): Response
    {
        $cities = City::query()
            ->withCount(['zones', 'bookings'])
            ->ordered()
            ->get();

        return Inertia::render('admin/cities/index', [
            'cities' => CityResource::collection($cities),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/cities/create', [
            'timezones' => $this->timezones(),
        ]);
    }

    public function store(StoreCityRequest $request, SaveCity $action): RedirectResponse
    {
        $action->handle(null, $request->validated());

        return to_route('admin.cities.index')->with('success', __('City created.'));
    }

    public function edit(City $city): Response
    {
        return Inertia::render('admin/cities/edit', [
            'city' => new CityResource($city),
            'timezones' => $this->timezones(),
        ]);
    }

    public function update(UpdateCityRequest $request, City $city, SaveCity $action): RedirectResponse
    {
        $action->handle($city, $request->validated());

        return to_route('admin.cities.index')->with('success', __('City updated.'));
    }

    public function destroy(City $city, DeleteCity $action): RedirectResponse
    {
        $action->handle($city);

        return to_route('admin.cities.index')->with('success', __('City deleted.'));
    }

    /**
     * @return list<string>
     */
    private function timezones(): array
    {
        return timezone_identifiers_list();
    }
}
