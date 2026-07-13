<?php

namespace App\Http\Requests\Provider;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('provider') ?? false;
    }

    /**
     * The destination is one of the provider's own saved accounts (M22). The
     * ownership clause is the guard; RequestPayout re-checks it anyway, because
     * an id in a payload is not a permission.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();

        return [
            'payout_account_id' => [
                'required',
                'integer',
                Rule::exists('payout_accounts', 'id')->where('provider_id', $user === null ? 0 : $user->id),
            ],
        ];
    }
}
