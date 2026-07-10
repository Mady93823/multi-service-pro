<?php

namespace App\Http\Requests\Provider;

use Illuminate\Foundation\Http\FormRequest;

class RequestPayoutRequest extends FormRequest
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
            'method' => ['required', 'in:upi,bank'],
            'upi_id' => ['required_if:method,upi', 'nullable', 'string', 'max:191'],
            'account_name' => ['required_if:method,bank', 'nullable', 'string', 'max:191'],
            'account_number' => ['required_if:method,bank', 'nullable', 'string', 'max:34'],
            'ifsc' => ['required_if:method,bank', 'nullable', 'string', 'max:20'],
        ];
    }

    /**
     * Only the fields the chosen method needs — a UPI payout must not carry a
     * half-filled bank block into the stored JSON.
     *
     * @return array<string, mixed>
     */
    public function methodDetails(): array
    {
        $method = (string) $this->validated('method');

        $fields = $method === 'upi'
            ? ['upi_id']
            : ['account_name', 'account_number', 'ifsc'];

        return ['method' => $method] + $this->safe()->only($fields);
    }
}
