<?php

namespace App\Http\Requests\Admin;

use App\Domain\Settings\Groups\SettingsGroup;
use App\Domain\Settings\SettingsGroupRegistry;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * Only the group being saved is validated (ADR D24) — a payload cannot
     * carry, and therefore cannot write, another group's keys.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->group()->rules($this->all());
    }

    public function group(): SettingsGroup
    {
        $registry = app(SettingsGroupRegistry::class);
        $group = $registry->get((string) $this->route('group'));

        abort_if($group === null, 404);

        return $group;
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
