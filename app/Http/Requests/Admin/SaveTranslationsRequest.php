<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SaveTranslationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route group enforces role:admin.
    }

    /**
     * Inertia submits the whole editor as one JSON body (never form fields —
     * the catalog is bigger than PHP's max_input_vars). Keys are English
     * source strings; SaveTranslations drops any key not in the catalog.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'translations' => ['required', 'array'],
            'translations.*' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
