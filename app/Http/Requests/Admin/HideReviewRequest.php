<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class HideReviewRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // The reason is shown to the review's author — hiding without saying
        // why is a support ticket waiting to happen.
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
