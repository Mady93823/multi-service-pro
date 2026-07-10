<?php

namespace App\Listeners;

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Events\BookingStatusChanged;
use App\Domain\Referrals\Actions\RewardReferral;
use App\Domain\Referrals\Enums\ReferralStatus;
use App\Domain\Settings\SettingsRegistry;
use App\Models\Referral;

/**
 * A referee's first COMPLETED booking is the reward trigger (M12) — sign-up
 * alone pays nothing. "First" needs no counting: the referral row is pending
 * exactly once, and RewardReferral flips it under a lock.
 *
 * Auto-discovered from the handle() type-hint. Do not also register it in a
 * provider (the M06 double-fire lesson).
 */
class RewardReferrerOnFirstCompletion
{
    public function __construct(
        private readonly RewardReferral $action,
        private readonly SettingsRegistry $settings,
    ) {}

    public function handle(BookingStatusChanged $event): void
    {
        if ($event->to !== BookingStatus::Completed) {
            return;
        }

        if (! $this->settings->boolean('referrals.enabled', true)) {
            return;
        }

        $referral = Referral::query()
            ->where('referee_id', $event->booking->customer_id)
            ->where('status', ReferralStatus::Pending->value)
            ->first();

        if ($referral !== null) {
            $this->action->handle($referral);
        }
    }
}
