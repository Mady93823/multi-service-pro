<?php

namespace Database\Factories;

use App\Models\Page;
use App\Models\PageBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageBlock>
 */
class PageBlockFactory extends Factory
{
    protected $model = PageBlock::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'page_id' => Page::factory(),
            'type' => 'rich_text',
            'payload' => ['body' => $this->faker->sentence(), 'width' => 'narrow'],
            'sort_order' => 1,
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }
}
