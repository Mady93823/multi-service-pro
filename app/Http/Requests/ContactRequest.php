<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:2000'],
            // Honeypot: a real visitor never sees this field, so anything in it
            // came from a bot. Validated as "must be empty" rather than dropped,
            // so a bot that fills every field gets an error, not a ticket.
            'website' => ['prohibited'],
        ];
    }
}
