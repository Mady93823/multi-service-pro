<?php

namespace App\Domain\Reviews\Actions;

use App\Domain\Bookings\Enums\BookingStatus;
use App\Domain\Reviews\Events\ReviewChanged;
use App\Models\Booking;
use App\Models\Review;
use App\Notifications\ReviewReceivedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitReview
{
    /**
     * The FormRequest and BookingPolicy already gate this, but the action
     * re-checks state under a lock — two tabs submitting at once must not
     * produce two reviews (the unique(booking_id) index is the last resort,
     * not the error path).
     *
     * @param  list<UploadedFile>  $photos
     */
    public function handle(Booking $booking, int $rating, ?string $comment, array $photos = []): Review
    {
        $review = DB::transaction(function () use ($booking, $rating, $comment, $photos): Review {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== BookingStatus::Completed) {
                throw ValidationException::withMessages([
                    'rating' => __('You can only review a completed booking.'),
                ]);
            }

            if ($locked->provider_id === null) {
                throw ValidationException::withMessages([
                    'rating' => __('This booking has no professional to review.'),
                ]);
            }

            if (Review::query()->where('booking_id', $locked->id)->exists()) {
                throw ValidationException::withMessages([
                    'rating' => __('You have already reviewed this booking.'),
                ]);
            }

            $review = Review::query()->create([
                'booking_id' => $locked->id,
                'customer_id' => $locked->customer_id,
                'provider_id' => $locked->provider_id,
                'rating' => $rating,
                'comment' => $comment,
            ]);

            foreach ($photos as $photo) {
                $review->addMedia($photo)->toMediaCollection('review_photos');
            }

            return $review;
        });

        ReviewChanged::dispatch($review);

        $review->provider?->notify(new ReviewReceivedNotification($review));

        return $review;
    }
}
