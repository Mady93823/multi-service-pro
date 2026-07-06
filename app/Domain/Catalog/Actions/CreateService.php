<?php

namespace App\Domain\Catalog\Actions;

use App\Models\Service;
use App\Support\UniqueSlug;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CreateService
{
    /**
     * @param  array{category_id: int, name: string, short_description?: string|null, description?: string|null, pricing_type: string, price: string|float, duration_minutes?: int|null, is_featured?: bool, is_active?: bool, sort_order?: int}  $data
     * @param  list<array{name: string, price: string|float, is_active?: bool}>  $addons
     * @param  list<int>  $relatedIds
     */
    public function handle(array $data, array $addons = [], array $relatedIds = [], ?UploadedFile $image = null): Service
    {
        $data['slug'] = UniqueSlug::for(Service::withTrashed(), $data['name']);

        $service = DB::transaction(function () use ($data, $addons, $relatedIds) {
            $service = Service::create($data);

            $service->addons()->createMany($addons);
            $service->related()->sync($relatedIds);

            return $service;
        });

        if ($image !== null) {
            $service->addMedia($image)->toMediaCollection('images');
        }

        return $service;
    }
}
