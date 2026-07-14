<?php

namespace App\Domain\Cities\Actions;

use App\Domain\Cities\CityDirectory;
use App\Models\City;
use Illuminate\Support\Str;

class SaveCity
{
    public function __construct(private readonly CityDirectory $directory) {}

    /**
     * Create or update a city. The slug is derived from the name when the
     * admin leaves it blank and deduped against every other city — it is the
     * storefront's handle on the town, so it may never collide.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(?City $city, array $data): City
    {
        $city ??= new City;

        $name = trim((string) $data['name']);
        $slug = trim((string) ($data['slug'] ?? ''));

        $city->fill([
            'name' => $name,
            'slug' => $this->uniqueSlug($slug !== '' ? $slug : $name, $city),
            'state' => $data['state'] ?? null,
            'timezone' => (string) $data['timezone'],
            'center_lat' => (float) $data['center_lat'],
            'center_lng' => (float) $data['center_lng'],
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        $city->save();

        $this->directory->flush();

        return $city;
    }

    private function uniqueSlug(string $source, City $city): string
    {
        $base = Str::slug($source) ?: 'city';
        $slug = $base;
        $suffix = 2;

        while (City::query()
            ->where('slug', $slug)
            ->when($city->exists, fn ($query) => $query->whereKeyNot($city->getKey()))
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
