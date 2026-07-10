<?php

use App\Domain\Bookings\Enums\PaymentMethod;
use Tests\Support\EarningsFixtures;
use Tests\Support\ReviewFixtures;

test('completing a job updates the provider jobs_completed counter', function () {
    [, , $provider] = ReviewFixtures::completedBooking();

    expect($provider->providerProfile()->sole()->jobs_completed)->toBe(1);

    // A second completed job for the same provider keeps the count honest.
    $booking = EarningsFixtures::booking(PaymentMethod::Wallet, null, $provider);
    EarningsFixtures::complete($booking);

    expect($provider->providerProfile()->sole()->jobs_completed)->toBe(2);
});
