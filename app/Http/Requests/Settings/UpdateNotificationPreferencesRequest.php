<?php

namespace App\Http\Requests\Settings;

use App\Http\Concerns\ResolvesNotificationPreferences;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    use ResolvesNotificationPreferences;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->preferenceRules();
    }
}
