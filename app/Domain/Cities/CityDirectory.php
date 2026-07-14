<?php

namespace App\Domain\Cities;

use App\Models\City;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The active cities, in display order — the storefront switcher reads this on
 * every request, so it is cached forever and flushed by SaveCity/DeleteCity
 * (same idiom as the footer pages and the site menus).
 */
class CityDirectory
{
    private const CACHE_KEY = 'cities.active';

    /**
     * @return Collection<int, City>
     */
    public function active(): Collection
    {
        try {
            /** @var Collection<int, City> */
            return Cache::rememberForever(
                self::CACHE_KEY,
                fn (): Collection => City::query()->active()->ordered()->get(),
            );
        } catch (Throwable) {
            // Table missing (installer, early boot) — no switcher beats a 500.
            return new Collection;
        }
    }

    public function find(int $id): ?City
    {
        return $this->active()->firstWhere('id', $id);
    }

    /**
     * The city a visitor lands in when nothing else has spoken for them.
     */
    public function default(): ?City
    {
        return $this->active()->first();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
