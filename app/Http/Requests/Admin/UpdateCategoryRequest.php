<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Category $category */
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:120'],
            // Two-level tree: only root categories may be parents; never itself.
            'parent_id' => [
                'nullable',
                'integer',
                Rule::notIn([$category->id]),
                Rule::exists('categories', 'id')->whereNull('parent_id')->whereNull('deleted_at'),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['boolean'],
            'icon' => ['nullable', 'image', 'max:2048'],
            'image' => ['nullable', 'image', 'max:4096'],
        ];
    }
}
