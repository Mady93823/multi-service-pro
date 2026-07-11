<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CloseTicketRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Optional — a close often follows a resolve that already holds one.
            'resolution_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
