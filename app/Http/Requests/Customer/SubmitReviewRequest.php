<?php

namespace App\Http\Requests\Customer;

use App\Domain\Settings\SettingsRegistry;
use App\Models\Booking;
use App\Support\UploadRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SubmitReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! app(SettingsRegistry::class)->boolean('reviews.enabled', true)) {
            return false;
        }

        return $this->user()?->can('review', $this->bookingParam()) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxPhotos = app(SettingsRegistry::class)->integer('reviews.max_photos', 4);

        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'photos' => ['array', 'max:'.$maxPhotos],
            'photos.*' => UploadRules::image(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxPhotos = app(SettingsRegistry::class)->integer('reviews.max_photos', 4);

        return [
            'photos.max' => __('You can attach up to :max photos.', ['max' => (string) $maxPhotos]),
            'photos.*.max' => __('Each photo must be 4 MB or smaller.'),
        ];
    }

    /**
     * One review per booking (the HANDOFF rule: guard in the FormRequest and
     * the policy) — SubmitReview re-checks under a lock for the race.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->bookingParam()->review()->exists()) {
                    $validator->errors()->add('rating', __('You have already reviewed this booking.'));
                }
            },
        ];
    }

    private function bookingParam(): Booking
    {
        $booking = $this->route('booking');

        assert($booking instanceof Booking);

        return $booking;
    }
}
