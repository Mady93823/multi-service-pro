<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Review photos live on the private disk but back public storefront cards,
 * so this route is guest-reachable — ReviewPolicy@view (nullable user)
 * decides, and a hidden review's photos 404 rather than 403 so moderation
 * leaves nothing to probe.
 */
class ReviewPhotoController extends Controller
{
    public function show(Review $review, Media $media): BinaryFileResponse
    {
        abort_unless(Gate::allows('view', $review), 404);

        abort_unless(
            $media->model_type === Review::class
            && (int) $media->model_id === $review->id
            && $media->collection_name === 'review_photos',
            404,
        );

        return response()->file($media->getPath());
    }
}
