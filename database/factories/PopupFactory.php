<?php

namespace Database\Factories;

use App\Domain\Marketing\Enums\PopupAudience;
use App\Models\Popup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Popup>
 */
class PopupFactory extends Factory
{
    protected $model = Popup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Monsoon offer',
            'body' => '**20% off** your first deep clean.',
            'link_url' => null,
            'link_label' => null,
            'audience' => PopupAudience::Everyone->value,
            'frequency_days' => 7,
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
        ];
    }
}
