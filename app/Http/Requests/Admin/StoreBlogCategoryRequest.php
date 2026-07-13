<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreBlogCategoryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['nullable', 'string', 'alpha_dash:ascii', 'max:80'],
            'description' => ['nullable', 'string', 'max:200'],
            'sort_order' => ['integer', 'min:0', 'max:65535'],
            'is_active' => ['boolean'],
        ];
    }
}
