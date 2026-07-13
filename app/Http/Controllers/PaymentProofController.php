<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Offline-payment proof lives on the private disk (M22) — this policy-checked
 * route is the only way to see it. Mirrors BookingPhotoController; a media id
 * that belongs to another payment is a 404, not someone else's receipt.
 */
class PaymentProofController extends Controller
{
    public function show(Payment $payment, Media $media): BinaryFileResponse
    {
        Gate::authorize('view', $payment);

        abort_unless(
            $media->model_type === Payment::class
            && (int) $media->model_id === $payment->id
            && $media->collection_name === 'proof',
            404,
        );

        return response()->file($media->getPath());
    }
}
