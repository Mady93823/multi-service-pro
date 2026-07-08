<?php

namespace App\Http\Requests\Admin;

use App\Domain\Providers\Enums\ProviderApprovalStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewProviderRequest extends FormRequest
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
            'status' => ['required', Rule::enum(ProviderApprovalStatus::class)],
            'note' => [
                'nullable',
                'string',
                'max:500',
                Rule::requiredIf(in_array($this->input('status'), [
                    ProviderApprovalStatus::Rejected->value,
                    ProviderApprovalStatus::Suspended->value,
                ], true)),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.required' => __('A note is required when rejecting or suspending a provider.'),
        ];
    }
}
