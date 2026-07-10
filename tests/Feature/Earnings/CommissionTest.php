<?php

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Earnings\Actions\RecordBookingEarning;
use App\Domain\Earnings\CommissionResolver;
use App\Domain\Earnings\Enums\EarningType;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Earning;
use App\Models\Service;
use App\Models\User;
use Tests\Support\EarningsFixtures;

beforeEach(function () {
    app(SettingsRegistry::class)->set('payments.commission_percent', '20');
});

test('the global rate applies when no category overrides it', function () {
    $split = app(CommissionResolver::class)->forBooking(EarningsFixtures::booking());

    expect($split)->toBe(['gross' => 500.0, 'commission' => 100.0, 'rate' => 20.0]);
});

test('a category rate overrides the global one', function () {
    $category = Category::factory()->create(['commission_percent' => '30']);
    $service = Service::factory()->create(['category_id' => $category->id, 'price' => 500]);

    $split = app(CommissionResolver::class)->forBooking(EarningsFixtures::booking(service: $service));

    expect($split['commission'])->toBe(150.0)
        ->and($split['rate'])->toBe(30.0);
});

test('a child category inherits its parent override, not the global rate', function () {
    $parent = Category::factory()->create(['commission_percent' => '35']);
    $child = Category::factory()->childOf($parent)->create(['commission_percent' => null]);
    $service = Service::factory()->create(['category_id' => $child->id, 'price' => 500]);

    expect(app(CommissionResolver::class)->forBooking(EarningsFixtures::booking(service: $service))['commission'])->toBe(175.0);
});

test('commission blends per-item rates and reports the effective percentage', function () {
    $cheap = Category::factory()->create(['commission_percent' => '10']);
    $dear = Category::factory()->create(['commission_percent' => '30']);

    $booking = Booking::factory()->status(BookingStatus::InProgress)->create([
        'provider_id' => User::factory()->provider()->create()->id,
    ]);

    foreach ([[$cheap, '300.00'], [$dear, '200.00']] as [$category, $price]) {
        $service = Service::factory()->create(['category_id' => $category->id, 'price' => $price]);
        $booking->items()->create([
            'service_id' => $service->id,
            'name_snapshot' => $service->name,
            'price_snapshot' => $price,
            'qty' => 1,
            'addons_snapshot' => [],
        ]);
    }

    // 300 @ 10% + 200 @ 30% = 90 on a 500 base.
    $split = app(CommissionResolver::class)->forBooking($booking);

    expect($split['commission'])->toBe(90.0)
        ->and($split['rate'])->toBe(18.0);
});

test('a category rate above 100 percent cannot take more than the whole job', function () {
    $category = Category::factory()->create(['commission_percent' => '999.99']);
    $service = Service::factory()->create(['category_id' => $category->id, 'price' => 500]);

    $split = app(CommissionResolver::class)->forBooking(EarningsFixtures::booking(service: $service));

    expect($split['commission'])->toBe(500.0)
        ->and($split['rate'])->toBe(100.0);
});

test('a soft-deleted service still prices its historical booking', function () {
    $category = Category::factory()->create(['commission_percent' => '30']);
    $service = Service::factory()->create(['category_id' => $category->id, 'price' => 500]);
    $booking = EarningsFixtures::booking(service: $service);

    $service->delete();
    $category->delete();

    expect(app(CommissionResolver::class)->forBooking($booking->fresh())['commission'])->toBe(150.0);
});

test('completing a booking snapshots the split onto it and appends one earning', function () {
    $booking = EarningsFixtures::complete(EarningsFixtures::booking());

    expect($booking->fresh()->commission_rate_snapshot)->toBe('20.00')
        ->and($booking->fresh()->commission_amount)->toBe('100.00')
        ->and($booking->fresh()->provider_earning)->toBe('400.00');

    $earning = Earning::query()->where('booking_id', $booking->id)->sole();

    expect($earning->type)->toBe(EarningType::Job)
        ->and($earning->gross)->toBe('500.00')
        ->and($earning->commission)->toBe('100.00')
        ->and($earning->net)->toBe('400.00');
});

test('a later rate change never rewrites a completed booking', function () {
    $booking = EarningsFixtures::complete(EarningsFixtures::booking());

    app(SettingsRegistry::class)->set('payments.commission_percent', '50');

    expect($booking->fresh()->commission_amount)->toBe('100.00')
        ->and(Earning::query()->where('booking_id', $booking->id)->sole()->commission)->toBe('100.00');
});

test('a re-fired completion listener does not pay the provider twice', function () {
    $booking = EarningsFixtures::complete(EarningsFixtures::booking());

    app(RecordBookingEarning::class)->handle($booking->fresh());

    expect(Earning::query()->where('booking_id', $booking->id)->count())->toBe(1);
});

test('a booking completed without a provider records no earning', function () {
    $booking = Booking::factory()->status(BookingStatus::InProgress)->create(['provider_id' => null]);

    expect(app(RecordBookingEarning::class)->handle($booking))->toBeNull()
        ->and(Earning::query()->where('booking_id', $booking->id)->count())->toBe(0);
});
