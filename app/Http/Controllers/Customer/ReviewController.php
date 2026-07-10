<?php

namespace App\Http\Controllers\Customer;

use App\Domain\Reviews\Actions\SubmitReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\SubmitReviewRequest;
use App\Models\Booking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;

class ReviewController extends Controller
{
    public function store(SubmitReviewRequest $request, Booking $booking, SubmitReview $action): RedirectResponse
    {
        /** @var array<int, UploadedFile> $photos */
        $photos = $request->file('photos', []);

        $action->handle(
            $booking,
            $request->integer('rating'),
            $request->filled('comment') ? (string) $request->string('comment') : null,
            array_values($photos),
        );

        return back()->with('success', __('Thanks for your review!'));
    }
}
