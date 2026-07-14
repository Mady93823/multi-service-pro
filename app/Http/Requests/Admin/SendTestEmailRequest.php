<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SendTestEmailRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:191'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Default to the admin asking: a test email is for proving the server,
        // not for mailing strangers from someone else's install.
        if (! $this->filled('email')) {
            $this->merge(['email' => $this->user()?->email]);
        }
    }
}
