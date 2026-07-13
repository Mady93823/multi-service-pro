<?php

namespace App\Http\Requests\Admin;

use App\Domain\Cms\Enums\MenuItemType;
use App\Domain\Cms\Enums\MenuVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMenuItemRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = $this->string('type')->toString();

        return [
            'label' => ['required', 'string', 'max:60'],
            'type' => ['required', Rule::enum(MenuItemType::class)],
            'visibility' => ['required', Rule::enum(MenuVisibility::class)],
            'parent_id' => ['nullable', 'integer', 'exists:menu_items,id'],
            'is_active' => ['boolean'],
            'target' => [
                'required',
                'string',
                'max:2048',
                // The target means whatever the type says it means, so it is
                // validated against that meaning — a route name must be one we
                // allow, a page must exist, a URL must not be a script sink.
                Rule::when(
                    $type === MenuItemType::Route->value,
                    [Rule::in(array_keys(MenuItemType::allowedRoutes()))],
                ),
                Rule::when(
                    $type === MenuItemType::Page->value,
                    ['exists:pages,slug'],
                ),
                Rule::when(
                    $type === MenuItemType::Url->value,
                    ['regex:#^(https?://|/)#'],
                ),
            ],
        ];
    }
}
