<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBankAccountRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:100'],
            'account_name' => ['nullable', 'string', 'max:191'],
            'account_number' => ['nullable', 'string', 'max:34'],
            'ifsc' => ['nullable', 'string', 'max:20'],
            'upi_id' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            // Optional payment QR, picked from the media library (M18).
            'media_asset_id' => ['nullable', 'integer', 'exists:media_assets,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        // An account with neither bank details nor a UPI id is instructions a
        // customer cannot follow.
        $validator->after(function (Validator $validator): void {
            $bank = trim((string) $this->input('account_number', ''));
            $upi = trim((string) $this->input('upi_id', ''));

            if ($bank === '' && $upi === '') {
                $validator->errors()->add('account_number', __('Enter a bank account number or a UPI id.'));
            }
        });
    }
}
