<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
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
            'app_name' => ['required', 'string', 'max:100'],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'currency' => ['required', 'string', 'size:3', 'alpha:ascii', 'uppercase'],
            'timezone' => ['required', 'timezone:all'],
            'locale' => ['required', 'string', 'min:2', 'max:10', 'regex:/^[a-z]{2}([_-][A-Za-z]{2,4})?$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'remove_logo' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'primary_color.regex' => __('The primary color must be a hex value like #4f46e5.'),
        ];
    }
}
