<?php

namespace App\Listeners;

use App\Domain\Reviews\Events\ReviewChanged;
use App\Models\ProviderProfile;
use App\Models\Review;

/**
 * Recomputes the denormalized rating columns on provider_profiles from the
 * visible reviews — a full recompute, not an increment, so hiding a review
 * pulls its star out of the average and a re-fired event stays idempotent.
 *
 * Auto-discovered from the handle() type-hint. Do not also register it in a
 * provider (the M06 double-fire lesson).
 */
class SyncProviderRatingOnReviewChange
{
    public function handle(ReviewChanged $event): void
    {
        $providerId = $event->review->provider_id;

        /** @var object{rating_count: int, rating_avg: float|string|null} $stats */
        $stats = Review::query()
            ->visible()
            ->where('provider_id', $providerId)
            ->selectRaw('count(*) as rating_count, coalesce(avg(rating), 0) as rating_avg')
            ->first();

        ProviderProfile::query()
            ->where('user_id', $providerId)
            ->update([
                'rating_avg' => round((float) $stats->rating_avg, 2),
                'rating_count' => (int) $stats->rating_count,
            ]);
    }
}
