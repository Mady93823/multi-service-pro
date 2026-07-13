<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSponsorRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            // An href is a script sink — http(s) only (banner-link rule).
            'link_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
            // A sponsor without a logo is an empty slot in the strip — required
            // on create, optional on edit (the existing logo stays).
            'media_asset_id' => [$this->isMethod('POST') ? 'required' : 'nullable', 'integer', 'exists:media_assets,id'],
        ];
    }
}
