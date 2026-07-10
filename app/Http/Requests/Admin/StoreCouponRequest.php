<?php

namespace App\Http\Requests\Admin;

use App\Domain\Coupons\Enums\CouponType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // admin group middleware guards the route
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('coupons', 'code')],
            'type' => ['required', Rule::enum(CouponType::class)],
            'value' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'max_discount' => ['nullable', 'numeric', 'min:0.01', 'max:100000'],
            'min_order' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'per_user_limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'first_order_only' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('type') === CouponType::Percent->value
                    && is_numeric($this->input('value'))
                    && (float) $this->input('value') > 100) {
                    $validator->errors()->add('value', __('A percentage discount cannot exceed 100.'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => __('Coupon codes may only contain letters, numbers, dashes and underscores.'),
        ];
    }
}
