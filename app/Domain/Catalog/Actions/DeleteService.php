<?php

namespace App\Domain\Catalog\Actions;

use App\Models\Service;

class DeleteService
{
    /**
     * Soft-deletes; cross-sell links are detached so other service pages
     * stop recommending it immediately.
     */
    public function handle(Service $service): void
    {
        $service->related()->detach();
        Service::query()
            ->whereHas('related', fn ($query) => $query->whereKey($service->getKey()))
            ->get()
            ->each(fn (Service $other) => $other->related()->detach($service->getKey()));

        $service->delete();
    }
}
