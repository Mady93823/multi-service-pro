<?php

namespace App\Http\Requests\Admin;

use App\Domain\Banners\Enums\BannerPlacement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // admin group middleware guards the route
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            // Scheme-restricted on purpose: a javascript: href rendered by
            // the storefront would be stored XSS.
            'link_url' => ['nullable', 'string', 'max:255', 'url:http,https'],
            'placement' => ['required', Rule::enum(BannerPlacement::class)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['boolean'],
            // M18: a banner takes its picture from the media library. Uploading
            // one here still works — it becomes a library asset first, so the
            // library is the whole inventory and not a second silo (D29).
            'image' => ['required_without:media_asset_id', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'media_asset_id' => ['required_without:image', 'nullable', 'integer', 'exists:media_assets,id'],
        ];
    }
}
