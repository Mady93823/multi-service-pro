<?php

namespace App\Domain\Reviews\Events;

use App\Models\Review;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired whenever a review's contribution to public ratings changes —
 * submission and moderation both. The rating recompute listens here so the
 * denormalized provider_profiles columns can never drift from the reviews
 * table (07-Conventions events rule).
 */
class ReviewChanged
{
    use Dispatchable;

    public function __construct(public readonly Review $review) {}
}
