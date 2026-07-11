<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLanguageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route group enforces role:admin.
    }

    /**
     * The code is immutable after creation — renaming a locale would orphan
     * its translation file. Only display fields and the active flag change.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'native_name' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ];
    }
}
