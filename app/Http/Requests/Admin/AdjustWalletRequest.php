<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdjustWalletRequest extends FormRequest
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
            'direction' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            // Manual money movement without a stated reason is indistinguishable
            // from theft in an audit — the note lands on the ledger row.
            'reason' => ['required', 'string', 'max:191'],
        ];
    }
}
