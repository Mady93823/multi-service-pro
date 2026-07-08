<?php

use App\Domain\Bookings\SlotGenerator;
use App\Domain\Settings\SettingsRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

// Timezone pinned to UTC so slot expectations read literally
// (the shipped default is Asia/Kolkata).

beforeEach(function () {
    app(SettingsRegistry::class)->set('localization.timezone', 'UTC');
    Carbon::setTestNow('2026-07-10 09:10:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

function slots(): SlotGenerator
{
    return app(SlotGenerator::class);
}

test('slots open only after the lead time', function () {
    $days = slots()->days();

    // 09:10 + 2h lead = 11:10 → first grid slot today is 12:00.
    expect($days[0]['date'])->toBe('2026-07-10')
        ->and($days[0]['slots'][0]['label'])->toBe('12:00 PM');
});

test('the grid respects day bounds and slot length', function () {
    $today = slots()->days()[1]; // full day, no lead-time trimming

    expect($today['slots'][0]['label'])->toBe('8:00 AM')
        ->and(end($today['slots'])['label'])->toBe('7:00 PM') // 19:00 + 60min = closing time
        ->and(count($today['slots']))->toBe(12);
});

test('the horizon stops at max days ahead', function () {
    $days = slots()->days();

    expect(end($days)['date'])->toBe('2026-07-17'); // today + 7
});

test('isBookable accepts exactly the offered grid', function () {
    // Aligned, future, inside the day window.
    expect(slots()->isBookable(CarbonImmutable::parse('2026-07-11 10:00:00', 'UTC')))->toBeTrue()
        // Inside the lead time.
        ->and(slots()->isBookable(CarbonImmutable::parse('2026-07-10 10:00:00', 'UTC')))->toBeFalse()
        // Off-grid minute.
        ->and(slots()->isBookable(CarbonImmutable::parse('2026-07-11 10:30:00', 'UTC')))->toBeFalse()
        // Before opening / at closing.
        ->and(slots()->isBookable(CarbonImmutable::parse('2026-07-11 07:00:00', 'UTC')))->toBeFalse()
        ->and(slots()->isBookable(CarbonImmutable::parse('2026-07-11 20:00:00', 'UTC')))->toBeFalse()
        // Past the booking horizon.
        ->and(slots()->isBookable(CarbonImmutable::parse('2026-07-18 10:00:00', 'UTC')))->toBeFalse();
});

test('slot settings drive the grid', function () {
    $settings = app(SettingsRegistry::class);
    $settings->set('booking.slot_minutes', 120);
    $settings->set('booking.day_starts', '09:00');
    $settings->set('booking.day_ends', '13:00');

    $day = slots()->days()[1];

    expect(array_column($day['slots'], 'label'))->toBe(['9:00 AM', '11:00 AM']);
});
