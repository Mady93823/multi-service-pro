<?php

namespace Tests\Support;

use App\Domain\Bookings\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\ProviderProfile;
use App\Models\Service;
use App\Models\User;

/**
 * Shared M10 fixtures — a class for the same --parallel reason as
 * EarningsFixtures. The provider always gets a profile row, because the
 * rating listener writes provider_profiles and a test without one would
 * assert against nothing.
 */
class ReviewFixtures
{
    /**
     * A completed booking ready to review, with a role-carrying customer
     * (the review route sits behind role:customer).
     *
     * @return array{0: Booking, 1: User, 2: User} booking, customer, provider
     */
    public static function completedBooking(?Service $service = null): array
    {
        $customer = User::factory()->customer()->create();
        $provider = User::factory()->provider()->create();
        ProviderProfile::factory()->approved()->create(['user_id' => $provider->id]);

        $service ??= Service::factory()->create(['price' => 500]);

        $booking = Booking::factory()->status(BookingStatus::InProgress)->create([
            'customer_id' => $customer->id,
            'provider_id' => $provider->id,
        ]);

        $booking->items()->create([
            'service_id' => $service->id,
            'name_snapshot' => $service->name,
            'price_snapshot' => '500.00',
            'qty' => 1,
            'addons_snapshot' => [],
        ]);

        EarningsFixtures::complete($booking);

        return [$booking->refresh(), $customer, $provider];
    }
}
