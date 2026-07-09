<?php

namespace App\Http\Requests\Provider;

use App\Domain\Tracking\GeoPing;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class RecordPingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'heading' => ['nullable', 'numeric', 'between:0,360'],
            'speed' => ['nullable', 'numeric', 'min:0'],
            'recorded_at' => ['nullable', 'date'],
        ];
    }

    public function toGeoPing(): GeoPing
    {
        $recordedAt = $this->validated('recorded_at');

        return new GeoPing(
            lat: (float) $this->validated('lat'),
            lng: (float) $this->validated('lng'),
            accuracy: $this->floatOrNull('accuracy'),
            heading: $this->floatOrNull('heading'),
            speed: $this->floatOrNull('speed'),
            recordedAt: $recordedAt === null ? null : CarbonImmutable::parse((string) $recordedAt),
        );
    }

    private function floatOrNull(string $key): ?float
    {
        $value = $this->validated($key);

        return $value === null ? null : (float) $value;
    }
}
