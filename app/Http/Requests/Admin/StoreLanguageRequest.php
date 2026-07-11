<?php

namespace App\Http\Requests\Admin;

use App\Models\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLanguageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route group enforces role:admin.
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('code');

        if (is_string($code)) {
            // "hi" / "pt-br" / "PT_BR" → language part lowercase; the strict
            // pattern below is the path-traversal guard (the code becomes
            // the lang/{code}.json filename).
            $this->merge(['code' => strtolower(trim($code))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:12',
                'regex:'.Language::CODE_PATTERN,
                Rule::unique('languages', 'code'),
            ],
            'name' => ['required', 'string', 'max:50'],
            'native_name' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ];
    }
}
