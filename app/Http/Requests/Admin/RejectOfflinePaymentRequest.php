<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RejectOfflinePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The customer is told this verbatim — a rejection with no reason is
            // a support ticket waiting to happen.
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
