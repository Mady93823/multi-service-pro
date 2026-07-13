<?php

namespace App\Http\Requests\Admin;

use App\Domain\Marketing\Enums\PopupAudience;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePopupRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            // Markdown source; the storefront never receives raw HTML (D20).
            'body' => ['nullable', 'string', 'max:2000'],
            'link_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'link_label' => ['nullable', 'string', 'max:40'],
            'audience' => ['required', Rule::enum(PopupAudience::class)],
            'frequency_days' => ['required', 'integer', 'min:0', 'max:365'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['boolean'],
            'media_asset_id' => ['nullable', 'integer', 'exists:media_assets,id'],
        ];
    }
}
