<?php

namespace App\Http\Requests\Customer;

use App\Domain\Settings\SettingsRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitOfflinePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Only an account that is actually on offer — a disabled one is not
            // a place the money can go.
            'bank_account_id' => ['required', 'integer', Rule::exists('bank_accounts', 'id')->where('is_active', true)],
            'reference' => ['nullable', 'string', 'max:100'],
            // A PDF receipt is as common as a screenshot; both stay on the
            // private disk. Proof is required when the admin cannot otherwise
            // check the transfer — make it mandatory, it is the whole point.
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'proof.required' => __('Please attach a screenshot or receipt of your transfer.'),
            'proof.max' => __('The proof must be 4 MB or smaller.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        // The kill switch belongs with the rest of the payment settings, not in
        // a route guard: the form simply is not offered when it is off.
        abort_unless(app(SettingsRegistry::class)->boolean('payments.offline_enabled', false), 404);
    }
}
