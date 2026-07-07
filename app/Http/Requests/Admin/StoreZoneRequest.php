<?php

namespace App\Http\Requests\Admin;

use App\Rules\GeoJsonPolygon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'geojson' => ['required', 'array', new GeoJsonPolygon],
            'is_active' => ['boolean'],
        ];
    }
}
