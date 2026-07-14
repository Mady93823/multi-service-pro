<?php

namespace App\Domain\Cities\Actions;

use App\Domain\Cities\CityDirectory;
use App\Models\City;
use Illuminate\Validation\ValidationException;

class DeleteCity
{
    public function __construct(private readonly CityDirectory $directory) {}

    /**
     * A city with zones cannot be deleted — every booking ever taken there
     * points at one of those zones, and the FK is `restrict` so the database
     * would refuse anyway. Deactivating is the reversible way to stop selling
     * in a town; deleting is only for one that never opened.
     */
    public function handle(City $city): void
    {
        if ($city->zones()->exists()) {
            throw ValidationException::withMessages([
                'city' => __('This city still has zones. Move or delete them first, or deactivate the city instead.'),
            ]);
        }

        $city->delete();

        $this->directory->flush();
    }
}
