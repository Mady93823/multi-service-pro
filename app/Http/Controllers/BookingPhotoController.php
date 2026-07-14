<?php

namespace App\Http\Controllers;

use App\Http\Concerns\ServesPrivateFiles;
use App\Models\Booking;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Booking photos live on the private disk (07-Conventions upload rules) —
 * this policy-checked route is the only way to view them.
 */
class BookingPhotoController extends Controller
{
    use ServesPrivateFiles;

    public function show(Booking $booking, Media $media): BinaryFileResponse
    {
        Gate::authorize('view', $booking);

        abort_unless(
            $media->model_type === Booking::class
            && (int) $media->model_id === $booking->id
            && $media->collection_name === 'problem_photos',
            404,
        );

        return $this->privateFile($media->getPath(), $media->mime_type, $media->file_name);
    }
}
