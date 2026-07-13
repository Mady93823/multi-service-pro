<?php

namespace Database\Factories;

use App\Domain\Cms\Enums\MenuItemType;
use App\Domain\Cms\Enums\MenuVisibility;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'menu_id' => Menu::factory(),
            'parent_id' => null,
            'label' => $this->faker->words(2, true),
            'type' => MenuItemType::Route->value,
            'target' => 'catalog.index',
            'visibility' => MenuVisibility::Everyone->value,
            'sort_order' => 1,
            'is_active' => true,
        ];
    }
}
