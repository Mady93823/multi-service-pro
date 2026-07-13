<?php

namespace App\Domain\Marketing\Actions;

use App\Models\Review;
use App\Models\Testimonial;
use RuntimeException;

/**
 * One click from the review queue (M10) to the storefront. The quote is copied,
 * not referenced: a customer editing or an admin hiding the review afterwards
 * must not silently change what the marketing page says — and `review_id` still
 * records where it came from (nullOnDelete, so deleting the review leaves the
 * testimonial standing).
 */
class PromoteReviewToTestimonial
{
    public function handle(Review $review): Testimonial
    {
        if ($review->is_hidden) {
            throw new RuntimeException('A hidden review cannot be promoted to a testimonial.');
        }

        $existing = Testimonial::query()->where('review_id', $review->id)->first();

        if ($existing instanceof Testimonial) {
            return $existing;
        }

        $review->loadMissing('customer');

        return Testimonial::query()->create([
            'review_id' => $review->id,
            'name' => $review->customer->name,
            'role' => null,
            'quote' => (string) $review->comment,
            'rating' => $review->rating,
            'sort_order' => (int) Testimonial::query()->max('sort_order') + 1,
            'is_active' => true,
        ]);
    }
}
