<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Zones\Actions\CreateZone;
use App\Domain\Zones\Actions\DeleteZone;
use App\Domain\Zones\Actions\UpdateZone;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreZoneRequest;
use App\Http\Requests\Admin\UpdateZoneRequest;
use App\Http\Resources\ZoneResource;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ZoneController extends Controller
{
    public function index(): Response
    {
        $zones = Zone::query()
            ->withCount(['services', 'addresses'])
            ->orderBy('city')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/zones/index', [
            'zones' => ZoneResource::collection($zones),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/zones/create');
    }

    public function store(StoreZoneRequest $request, CreateZone $action): RedirectResponse
    {
        $action->handle($request->validated());

        return to_route('admin.zones.index')->with('success', __('Zone created.'));
    }

    public function edit(Zone $zone): Response
    {
        return Inertia::render('admin/zones/edit', [
            'zone' => new ZoneResource($zone),
        ]);
    }

    public function update(UpdateZoneRequest $request, Zone $zone, UpdateZone $action): RedirectResponse
    {
        $action->handle($zone, $request->validated());

        return to_route('admin.zones.index')->with('success', __('Zone updated.'));
    }

    public function destroy(Zone $zone, DeleteZone $action): RedirectResponse
    {
        $action->handle($zone);

        return to_route('admin.zones.index')->with('success', __('Zone deleted.'));
    }
}
