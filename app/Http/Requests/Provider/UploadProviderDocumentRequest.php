<?php

namespace App\Http\Requests\Provider;

use App\Domain\Providers\Enums\ProviderDocumentType;
use App\Support\UploadRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadProviderDocumentRequest extends FormRequest
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
            'type' => ['required', Rule::enum(ProviderDocumentType::class)],
            'file' => ['required', ...UploadRules::document()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.max' => __('Each document must be 4 MB or smaller.'),
            'file.mimes' => __('Documents must be a JPG, PNG, WEBP image or a PDF.'),
        ];
    }
}
