<?php

namespace App\Http\Requests\Installer;

use Illuminate\Foundation\Http\FormRequest;

class StoreDatabaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // wizard access is gated by EnsureInstalled
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Not decoration: this lands in APP_NAME, and `config:clear` plus a
            // fresh boot means SettingsSeeder reads it back as branding.app_name
            // on the very next step. The buyer names their platform here.
            'app_name' => ['required', 'string', 'max:255'],
            'app_url' => ['required', 'url'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:64'],
            'username' => ['required', 'string', 'max:64'],
            'password' => ['nullable', 'string', 'max:255'],
        ];
    }
}
