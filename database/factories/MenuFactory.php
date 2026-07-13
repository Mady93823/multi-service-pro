<?php

namespace Database\Factories;

use App\Domain\Cms\Enums\MenuLocation;
use App\Models\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Menu>
 */
class MenuFactory extends Factory
{
    protected $model = Menu::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'location' => MenuLocation::Header->value,
            'name' => MenuLocation::Header->label(),
        ];
    }

    public function location(MenuLocation $location): self
    {
        return $this->state(fn (): array => [
            'location' => $location->value,
            'name' => $location->label(),
        ]);
    }
}
