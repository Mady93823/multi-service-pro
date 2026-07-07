<?php

namespace App\Domain\Zones\Actions;

use App\Domain\Zones\ZoneResolver;
use App\Models\Address;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Re-resolves zone assignment for addresses affected by a zone change:
 * unassigned addresses (a new/grown polygon may now cover them) and,
 * when a zone id is given, addresses currently assigned to that zone
 * (a shrunk polygon may have dropped them).
 */
class ResolveAddressZones
{
    public function __construct(private ZoneResolver $resolver) {}

    public function handle(?int $zoneId = null): void
    {
        Address::query()
            ->where(function (Builder $query) use ($zoneId) {
                $query->whereNull('zone_id')
                    ->when($zoneId !== null, fn (Builder $inner) => $inner->orWhere('zone_id', $zoneId));
            })
            ->chunkById(200, function (Collection $addresses) {
                foreach ($addresses as $address) {
                    $resolved = $this->resolver->resolve((float) $address->lat, (float) $address->lng)?->id;

                    if ($resolved !== $address->zone_id) {
                        $address->forceFill(['zone_id' => $resolved])->save();
                    }
                }
            });
    }
}
