<?php

namespace App\Http\Requests\Provider;

use Illuminate\Foundation\Http\FormRequest;

class StorePayoutAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('provider') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:upi,bank'],
            'label' => ['nullable', 'string', 'max:100'],
            'upi_id' => ['required_if:type,upi', 'nullable', 'string', 'max:191'],
            'account_name' => ['required_if:type,bank', 'nullable', 'string', 'max:191'],
            'account_number' => ['required_if:type,bank', 'nullable', 'string', 'max:34'],
            'ifsc' => ['required_if:type,bank', 'nullable', 'string', 'max:20'],
            'is_default' => ['boolean'],
        ];
    }
}
