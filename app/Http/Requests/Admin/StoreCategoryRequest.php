<?php

namespace App\Http\Requests\Admin;

use App\Domain\Catalog\Enums\CategoryType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:120'],
            // Two-level tree: only root categories may be parents.
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('parent_id')->whereNull('deleted_at')],
            // Which storefront surface (services page vs Event Management).
            // Only meaningful on roots — a child always inherits its parent's.
            'type' => ['nullable', Rule::enum(CategoryType::class)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['boolean'],
            'icon' => ['nullable', 'image', 'max:2048'],
            'image' => ['nullable', 'image', 'max:4096'],
            // Blank inherits the parent, then payments.commission_percent (M09).
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
