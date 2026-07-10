<?php

namespace Tests\Support;

use App\Domain\Bookings\SlotGenerator;
use App\Models\Address;
use App\Models\Service;
use App\Models\User;

/**
 * Shared M12 checkout helpers. A class rather than Pest global functions,
 * because `--parallel` loads test files independently and a helper declared
 * in one of them is not guaranteed to exist in another.
 */
class CheckoutFixtures
{
    /**
     * Customer with a default NYC address (clear of the seeded demo zones).
     *
     * @return array{0: User, 1: Address}
     */
    public static function customer(): array
    {
        $customer = User::factory()->customer()->create();
        $address = Address::factory()->for($customer)->at(40.7128, -74.0060)->default()->create();

        return [$customer, $address];
    }

    public static function slot(): string
    {
        $days = app(SlotGenerator::class)->days();

        return $days[0]['slots'][0]['value'];
    }

    public static function service(float $price = 500.0): Service
    {
        return Service::factory()->create(['price' => number_format($price, 2, '.', '')]);
    }
}
