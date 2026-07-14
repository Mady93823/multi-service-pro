<?php

namespace App\Http\Requests\Admin;

use App\Domain\Comms\Actions\SendAnnouncement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendAnnouncementRequest extends FormRequest
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
            'segment' => ['required', Rule::in(SendAnnouncement::SEGMENTS)],
            'title' => ['required', 'string', 'max:100'],
            'message' => ['required', 'string', 'max:500'],
            // An href is a script sink (D30) — the same rule banner links carry.
            'url' => ['nullable', 'url:http,https', 'max:2048'],
        ];
    }
}
