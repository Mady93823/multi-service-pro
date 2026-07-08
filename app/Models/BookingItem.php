<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Name/price columns are snapshots taken at checkout — catalog edits must
 * never rewrite a placed booking (04-Database-Schema integrity rules).
 *
 * @property list<array{id: int, name: string, price: string}>|null $addons_snapshot
 */
class BookingItem extends Model
{
    protected $fillable = [
        'service_id',
        'name_snapshot',
        'price_snapshot',
        'qty',
        'addons_snapshot',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_snapshot' => 'decimal:2',
            'qty' => 'integer',
            'addons_snapshot' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Line total from snapshots: (unit price + addons) × qty.
     */
    public function lineTotal(): string
    {
        $addons = array_sum(array_map(
            fn (array $addon): float => (float) $addon['price'],
            $this->addons_snapshot ?? [],
        ));

        return number_format(((float) $this->price_snapshot + $addons) * $this->qty, 2, '.', '');
    }
}
