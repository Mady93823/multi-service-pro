<?php

namespace App\Http\Requests\Admin;

use App\Support\UploadRules;

class UpdateBannerRequest extends StoreBannerRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        // Keeping the current image is the common edit; blank = keep, whether
        // the picture came from an upload or from the library (M18).
        $rules['image'] = ['nullable', ...UploadRules::image()];
        $rules['media_asset_id'] = ['nullable', 'integer', 'exists:media_assets,id'];

        return $rules;
    }
}
