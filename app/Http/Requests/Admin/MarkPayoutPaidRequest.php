<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MarkPayoutPaidRequest extends FormRequest
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
            // The bank transfer id. Required: a payout with no paper trail is
            // not a payout.
            'reference' => ['required', 'string', 'max:100'],
        ];
    }
}
