<?php

namespace App\Models;

use Database\Factories\CityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class City extends Model
{
    /** @use HasFactory<CityFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'state',
        'timezone',
        'center_lat',
        'center_lng',
        'is_active',
        'cash_enabled',
        'sort_order',
    ];

    /**
     * A city inserted with the column default still has to read `true` back on
     * the instance that inserted it (landmine: a DB default is not hydrated).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'cash_enabled' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'center_lat' => 'float',
            'center_lng' => 'float',
            'is_active' => 'boolean',
            'cash_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<Zone, $this>
     */
    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }

    /**
     * Everything ever booked in the city, through its zones — the booking
     * carries `zone_id`, so no extra column has to be kept in step.
     *
     * @return HasManyThrough<Booking, Zone, $this>
     */
    public function bookings(): HasManyThrough
    {
        return $this->hasManyThrough(Booking::class, Zone::class);
    }

    /**
     * @param  Builder<City>  $query
     * @return Builder<City>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<City>  $query
     * @return Builder<City>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
