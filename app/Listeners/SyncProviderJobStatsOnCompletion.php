<?php

namespace App\Listeners;

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Bookings\Events\BookingStatusChanged;
use App\Models\Booking;
use App\Models\ProviderProfile;

/**
 * Keeps provider_profiles.jobs_completed current — the provider card shows
 * it next to the rating. A recompute rather than an increment, for the same
 * idempotency reason as SyncProviderRatingOnReviewChange.
 *
 * Auto-discovered from the handle() type-hint. Do not also register it in a
 * provider (the M06 double-fire lesson).
 */
class SyncProviderJobStatsOnCompletion
{
    public function handle(BookingStatusChanged $event): void
    {
        if ($event->to !== BookingStatus::Completed || $event->booking->provider_id === null) {
            return;
        }

        $completed = Booking::query()
            ->where('provider_id', $event->booking->provider_id)
            ->where('status', BookingStatus::Completed->value)
            ->count();

        ProviderProfile::query()
            ->where('user_id', $event->booking->provider_id)
            ->update(['jobs_completed' => $completed]);
    }
}
