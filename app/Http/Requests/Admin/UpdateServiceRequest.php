<?php

namespace App\Http\Requests\Admin;

use App\Domain\Catalog\Enums\PricingType;
use App\Models\Service;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRequest extends FormRequest
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
        /** @var Service $service */
        $service = $this->route('service');

        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:150'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'pricing_type' => ['required', Rule::enum(PricingType::class)],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'is_featured' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'addons' => ['array', 'max:20'],
            'addons.*.name' => ['required', 'string', 'max:120'],
            'addons.*.price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'addons.*.is_active' => ['boolean'],
            'related_ids' => ['array', 'max:10'],
            'related_ids.*' => [
                'integer',
                'distinct',
                Rule::notIn([$service->id]),
                Rule::exists('services', 'id')->whereNull('deleted_at'),
            ],
            'zone_ids' => ['array'],
            'zone_ids.*' => ['integer', 'distinct', Rule::exists('zones', 'id')],
            'image' => ['nullable', 'image', 'max:4096'],
        ];
    }
}
