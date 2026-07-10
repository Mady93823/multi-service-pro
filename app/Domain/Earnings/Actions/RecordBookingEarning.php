<?php

namespace App\Domain\Earnings\Actions;

use App\Domain\Bookings\Enums\PaymentMethod;
use App\Domain\Earnings\CommissionResolver;
use App\Domain\Earnings\Enums\EarningStatus;
use App\Domain\Earnings\Enums\EarningType;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Booking;
use App\Models\Earning;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Splits a completed booking into commission and provider earning, snapshots
 * both onto the booking and appends the provider's ledger row (M09).
 *
 * The signed `net` is the whole point:
 *
 *   online   net = gross − commission            (the platform holds the money)
 *   cash     net = gross − commission − total    (the provider already took it)
 *
 * A cash job therefore lands negative — the provider owes the platform its
 * commission **and** the GST they collected at the door. Never clamp it.
 */
class RecordBookingEarning
{
    public function __construct(
        private readonly CommissionResolver $resolver,
        private readonly SettingsRegistry $settings,
    ) {}

    public function handle(Booking $booking): ?Earning
    {
        if ($booking->provider_id === null) {
            return null;
        }

        return DB::transaction(function () use ($booking): Earning {
            Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            $existing = Earning::query()
                ->where('booking_id', $booking->id)
                ->where('type', EarningType::Job->value)
                ->first();

            // A re-fired completion listener must not pay the provider twice.
            if ($existing !== null) {
                return $existing;
            }

            ['gross' => $gross, 'commission' => $commission, 'rate' => $rate] = $this->resolver->forBooking($booking);

            $collected = $booking->payment_method === PaymentMethod::Cash ? (float) $booking->total : 0.0;
            $net = round($gross - $commission - $collected, 2);
            $releaseNow = $this->releasesImmediately($net);

            $earning = Earning::query()->create([
                'provider_id' => $booking->provider_id,
                'booking_id' => $booking->id,
                'type' => EarningType::Job,
                'gross' => Money::decimal($gross),
                'commission' => Money::decimal($commission),
                'collected_amount' => Money::decimal($collected),
                'net' => Money::decimal($net),
                'commission_rate' => Money::decimal($rate),
                'status' => $releaseNow ? EarningStatus::Available : EarningStatus::Pending,
                'available_at' => $this->availableAt($releaseNow),
            ]);

            $booking->forceFill([
                'commission_rate_snapshot' => Money::decimal($rate),
                'commission_amount' => Money::decimal($commission),
                'provider_earning' => Money::decimal($net),
            ])->save();

            return $earning;
        });
    }

    /**
     * Positive earnings wait out payouts.hold_days — the window in which a
     * refund can still reverse them. A debt does not wait.
     */
    private function releasesImmediately(float $net): bool
    {
        return $net < 0 || $this->holdDays() === 0;
    }

    private function availableAt(bool $releaseNow): Carbon
    {
        return $releaseNow ? now() : now()->addDays($this->holdDays());
    }

    private function holdDays(): int
    {
        return max(0, $this->settings->integer('payouts.hold_days', 7));
    }
}
