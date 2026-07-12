<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'max:10'],
            // Raster only: the library generates webp conversions, and an SVG is
            // a script container the storefront would serve inline.
            'files.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }
}
