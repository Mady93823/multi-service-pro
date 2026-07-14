<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SaveEmailTemplateRequest extends FormRequest
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
            'subject' => ['required', 'string', 'max:191'],
            // Markdown source. It is rendered through MarkdownRenderer (D20),
            // so raw HTML never survives — no sanitizing rule needed here.
            'body' => ['required', 'string', 'max:10000'],
            'is_enabled' => ['boolean'],
        ];
    }
}
