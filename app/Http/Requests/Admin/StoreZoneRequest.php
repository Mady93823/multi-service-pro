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
            // M25: a zone belongs to a city row, not to a typed-in name — two
            // spellings of the same town used to be two cities.
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'name' => ['required', 'string', 'max:120'],
            'geojson' => ['required', 'array', new GeoJsonPolygon],
            'is_active' => ['boolean'],
            // D43: whether pay-after-service (cash) is offered in this zone.
            'cash_enabled' => ['boolean'],
        ];
    }
}
