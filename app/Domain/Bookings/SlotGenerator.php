<?php

namespace App\Domain\Bookings;

use App\Domain\Settings\SettingsRegistry;
use Carbon\CarbonImmutable;

/**
 * Settings-driven slot grid (booking.* group): fixed-length slots between
 * day_starts and day_ends, offered from lead_time_hours ahead up to
 * max_days_ahead days out. All math runs in the platform timezone; slot
 * values travel as UTC ISO strings.
 */
class SlotGenerator
{
    public function __construct(private readonly SettingsRegistry $settings) {}

    /**
     * Bookable days with their open slots, for the checkout picker.
     * Days with no remaining slots are omitted.
     *
     * @return list<array{date: string, label: string, slots: list<array{value: string, label: string}>}>
     */
    public function days(): array
    {
        $now = CarbonImmutable::now($this->timezone());
        $earliest = $now->addHours($this->settings->integer('booking.lead_time_hours', 2));

        $days = [];

        for ($offset = 0; $offset <= $this->settings->integer('booking.max_days_ahead', 7); $offset++) {
            $day = $now->addDays($offset);
            $slots = [];

            foreach ($this->slotStartsFor($day) as $start) {
                if ($start->lessThan($earliest)) {
                    continue;
                }

                $slots[] = [
                    'value' => $start->utc()->toIso8601String(),
                    'label' => $start->format('g:i A'),
                ];
            }

            if ($slots !== []) {
                $days[] = [
                    'date' => $day->toDateString(),
                    'label' => $day->format('D, j M'),
                    'slots' => $slots,
                ];
            }
        }

        return $days;
    }

    /**
     * Server-side validation of a submitted slot: on the grid, inside the
     * day window, past the lead time, within the booking horizon.
     */
    public function isBookable(CarbonImmutable $scheduledAtUtc): bool
    {
        $local = $scheduledAtUtc->setTimezone($this->timezone());
        $now = CarbonImmutable::now($this->timezone());

        if ($local->lessThan($now->addHours($this->settings->integer('booking.lead_time_hours', 2)))) {
            return false;
        }

        $horizon = $now->addDays($this->settings->integer('booking.max_days_ahead', 7))->endOfDay();

        if ($local->greaterThan($horizon)) {
            return false;
        }

        foreach ($this->slotStartsFor($local) as $start) {
            if ($start->equalTo($local)) {
                return true;
            }
        }

        return false;
    }

    public function slotMinutes(): int
    {
        return max(15, $this->settings->integer('booking.slot_minutes', 60));
    }

    /**
     * All grid slot starts for the given day (ignores lead time).
     *
     * @return list<CarbonImmutable>
     */
    private function slotStartsFor(CarbonImmutable $day): array
    {
        $dayStart = $day->startOfDay()->setTimeFromTimeString($this->settings->string('booking.day_starts', '08:00'));
        $dayEnd = $day->startOfDay()->setTimeFromTimeString($this->settings->string('booking.day_ends', '20:00'));
        $minutes = $this->slotMinutes();

        $starts = [];

        for ($start = $dayStart; $start->addMinutes($minutes)->lessThanOrEqualTo($dayEnd); $start = $start->addMinutes($minutes)) {
            $starts[] = $start;
        }

        return $starts;
    }

    private function timezone(): string
    {
        return $this->settings->string('localization.timezone', 'Asia/Kolkata');
    }
}
