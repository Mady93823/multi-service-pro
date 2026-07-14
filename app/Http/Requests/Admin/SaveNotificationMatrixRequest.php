<?php

namespace App\Http\Requests\Admin;

use App\Http\Concerns\ResolvesNotificationPreferences;
use Illuminate\Foundation\Http\FormRequest;

class SaveNotificationMatrixRequest extends FormRequest
{
    use ResolvesNotificationPreferences;

    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->preferenceRules();
    }
}
