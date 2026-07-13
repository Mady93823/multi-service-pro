<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTestimonialRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'max:100'],
            'quote' => ['required', 'string', 'max:1000'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
            // The picture is always a library asset (D29): the MediaPicker
            // uploads through the library and hands back an id, so this form
            // never carries a file.
            'media_asset_id' => ['nullable', 'integer', 'exists:media_assets,id'],
        ];
    }
}
