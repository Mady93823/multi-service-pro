<?php

namespace App\Domain\Reviews\Actions;

use App\Domain\Reviews\Events\ReviewChanged;
use App\Models\Review;

/**
 * Admin moderation. Hiding never deletes — the customer keeps their review,
 * the public (and the provider's rating) stop seeing it. Photos need no
 * separate handling: the photo route checks the review's visibility.
 */
class ModerateReview
{
    public function hide(Review $review, string $reason): Review
    {
        $review->forceFill([
            'is_hidden' => true,
            'hidden_reason' => $reason,
        ])->save();

        ReviewChanged::dispatch($review);

        return $review;
    }

    public function unhide(Review $review): Review
    {
        $review->forceFill([
            'is_hidden' => false,
            'hidden_reason' => null,
        ])->save();

        ReviewChanged::dispatch($review);

        return $review;
    }
}
