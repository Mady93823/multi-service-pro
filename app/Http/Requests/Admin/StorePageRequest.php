<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route group enforces role:admin.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            // Blank slug = derive from title (SavePage). alpha_dash keeps it
            // URL-safe; uniqueness is handled by SavePage's suffix loop.
            'slug' => ['nullable', 'string', 'alpha_dash:ascii', 'max:150'],
            'body' => ['required', 'string', 'max:65535'],
            'is_published' => ['boolean'],
            'show_in_footer' => ['boolean'],
            'sort_order' => ['integer', 'min:0', 'max:65535'],
        ];
    }
}
