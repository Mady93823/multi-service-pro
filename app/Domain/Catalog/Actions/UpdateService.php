<?php

namespace App\Domain\Catalog\Actions;

use App\Models\Service;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class UpdateService
{
    /**
     * Slug intentionally stays stable on rename — public URLs keep working.
     * Addons are replaced wholesale (safe pre-bookings; booking_items
     * snapshot prices later, M04).
     *
     * @param  array{category_id: int, name: string, short_description?: string|null, description?: string|null, pricing_type: string, price: string|float, duration_minutes?: int|null, is_featured?: bool, is_active?: bool, sort_order?: int}  $data
     * @param  list<array{name: string, price: string|float, is_active?: bool}>  $addons
     * @param  list<int>  $relatedIds
     * @param  list<int>  $zoneIds  empty = available in every zone
     */
    public function handle(Service $service, array $data, array $addons = [], array $relatedIds = [], array $zoneIds = [], ?UploadedFile $image = null): Service
    {
        DB::transaction(function () use ($service, $data, $addons, $relatedIds, $zoneIds) {
            $service->update($data);

            $service->addons()->delete();
            $service->addons()->createMany($addons);

            $service->related()->sync($relatedIds);
            $service->zones()->sync($zoneIds);
        });

        if ($image !== null) {
            $service->clearMediaCollection('images');
            $service->addMedia($image)->toMediaCollection('images');
        }

        return $service->refresh();
    }
}
