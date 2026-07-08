<?php

namespace App\Http\Requests\Provider;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProviderProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bio' => ['nullable', 'string', 'max:1000'],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:60'],
            'base_lat' => ['required', 'numeric', 'between:-90,90'],
            'base_lng' => ['required', 'numeric', 'between:-180,180'],
            'service_radius_km' => ['required', 'integer', 'min:1', 'max:100'],
            'working_hours' => ['required', 'array:mon,tue,wed,thu,fri,sat,sun'],
            'working_hours.*' => ['required', 'array'],
            'working_hours.*.off' => ['required', 'boolean'],
            'working_hours.*.start' => ['required_unless:working_hours.*.off,true', 'nullable', 'date_format:H:i'],
            'working_hours.*.end' => ['required_unless:working_hours.*.off,true', 'nullable', 'date_format:H:i', 'after:working_hours.*.start'],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => [
                'integer',
                Rule::exists('categories', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'base_lat.required' => __('Pick your base location on the map.'),
            'base_lng.required' => __('Pick your base location on the map.'),
            'working_hours.*.end.after' => __('End time must be after the start time.'),
            'category_ids.required' => __('Choose at least one service category.'),
            'category_ids.min' => __('Choose at least one service category.'),
        ];
    }
}
