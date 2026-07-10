<?php

namespace App\Http\Requests\Admin;

class UpdateBannerRequest extends StoreBannerRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        // Keeping the current image is the common edit; blank = keep.
        $rules['image'] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'];

        return $rules;
    }
}
