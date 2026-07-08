<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Guests may build a cart too — no auth requirement here.
 */
class AddToCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'service_id' => [
                'required',
                'integer',
                Rule::exists('services', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'qty' => ['required', 'integer', 'min:1', 'max:10'],
            'addon_ids' => ['array'],
            'addon_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('service_addons', 'id')
                    ->where('service_id', $this->integer('service_id'))
                    ->where('is_active', true),
            ],
        ];
    }
}
