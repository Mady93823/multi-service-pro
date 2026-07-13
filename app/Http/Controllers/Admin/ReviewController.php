<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Marketing\Actions\PromoteReviewToTestimonial;
use App\Domain\Reviews\Actions\ModerateReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HideReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function __construct(private readonly ModerateReview $moderation) {}

    public function index(Request $request): Response
    {
        $visibility = (string) $request->string('visibility');
        $rating = $request->integer('rating');

        $reviews = Review::query()
            ->with(['customer:id,name', 'provider:id,name', 'booking:id,code', 'media'])
            ->when($visibility === 'visible', fn ($query) => $query->where('is_hidden', false))
            ->when($visibility === 'hidden', fn ($query) => $query->where('is_hidden', true))
            ->when($rating >= 1 && $rating <= 5, fn ($query) => $query->where('rating', $rating))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/reviews/index', [
            'reviews' => ReviewResource::collection($reviews),
            'filters' => [
                'visibility' => $visibility,
                'rating' => $rating >= 1 && $rating <= 5 ? $rating : null,
            ],
        ]);
    }

    public function hide(HideReviewRequest $request, Review $review): RedirectResponse
    {
        $this->moderation->hide($review, (string) $request->validated('reason'));

        return back()->with('success', __('Review hidden.'));
    }

    public function unhide(Review $review): RedirectResponse
    {
        $this->moderation->unhide($review);

        return back()->with('success', __('Review restored.'));
    }

    /**
     * M19: one click from the moderation queue to the storefront. The quote is
     * copied, not referenced — see PromoteReviewToTestimonial.
     */
    public function promote(Review $review, PromoteReviewToTestimonial $action): RedirectResponse
    {
        if ($review->is_hidden) {
            return back()->with('error', __('A hidden review cannot be promoted.'));
        }

        $action->handle($review);

        return back()->with('success', __('Review promoted to a testimonial.'));
    }
}
