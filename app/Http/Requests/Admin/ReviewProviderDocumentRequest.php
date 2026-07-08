<?php

namespace App\Http\Requests\Admin;

use App\Domain\Providers\Enums\ProviderDocumentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewProviderDocumentRequest extends FormRequest
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
            'status' => ['required', Rule::in([
                ProviderDocumentStatus::Approved->value,
                ProviderDocumentStatus::Rejected->value,
            ])],
            'reject_reason' => [
                'nullable',
                'string',
                'max:500',
                Rule::requiredIf($this->input('status') === ProviderDocumentStatus::Rejected->value),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reject_reason.required' => __('Explain why the document was rejected.'),
        ];
    }
}
