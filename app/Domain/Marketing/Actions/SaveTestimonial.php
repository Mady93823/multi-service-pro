<?php

namespace App\Domain\Marketing\Actions;

use App\Domain\Media\Actions\AttachLibraryAsset;
use App\Models\MediaAsset;
use App\Models\Testimonial;

class SaveTestimonial
{
    public function __construct(private readonly AttachLibraryAsset $attach) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?MediaAsset $asset = null, ?Testimonial $testimonial = null): Testimonial
    {
        if ($testimonial === null) {
            $testimonial = Testimonial::query()->create($data);
        } else {
            $testimonial->update($data);
        }

        // The avatar always comes from the library (D29): an upload on this form
        // becomes a library asset first, so the manager sees every image.
        if ($asset !== null) {
            $this->attach->handle($testimonial, $asset, 'avatar');
        }

        return $testimonial;
    }
}
