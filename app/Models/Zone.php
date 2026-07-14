<?php

namespace App\Models;

use App\Domain\Zones\PointInPolygon;
use Database\Factories\ZoneFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, mixed>|null $geojson GeoJSON Polygon geometry (json column + array cast)
 */
class Zone extends Model
{
    /** @use HasFactory<ZoneFactory> */
    use HasFactory;

    protected $fillable = [
        'city_id',
        'name',
        'geojson',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'geojson' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * @return BelongsToMany<Service, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }

    /**
     * @return HasMany<Address, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * A zone only serves while its city does (M25): switching a city off is
     * the one control that stops a whole town being sold to, and it must not
     * mean editing every zone in it. Historical bookings keep their `zone_id`
     * and are untouched — this scope gates what is *offered*, never what was.
     *
     * @param  Builder<Zone>  $query
     * @return Builder<Zone>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereHas('city', fn (Builder $city) => $city->where('is_active', true));
    }

    public function contains(float $lat, float $lng): bool
    {
        return is_array($this->geojson) && PointInPolygon::contains($this->geojson, $lat, $lng);
    }
}
