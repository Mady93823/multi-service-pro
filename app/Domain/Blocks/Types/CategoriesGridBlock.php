<?php

namespace App\Domain\Blocks\Types;

use App\Domain\Blocks\Block;
use App\Domain\Blocks\BlockContext;
use App\Domain\Blocks\BlockField;
use App\Models\Category;

class CategoriesGridBlock extends Block
{
    public function type(): string
    {
        return 'categories_grid';
    }

    public function label(): string
    {
        return __('Categories grid');
    }

    public function fields(): array
    {
        return [
            BlockField::text('heading', __('Heading'), __('Browse by category')),
            BlockField::number('limit', __('How many'), 6),
        ];
    }

    public function rules(): array
    {
        return [
            'heading' => ['nullable', 'string', 'max:120'],
            'limit' => ['required', 'integer', 'min:1', 'max:24'],
        ];
    }

    public function data(array $payload, BlockContext $context): ?array
    {
        return [
            'heading' => $this->nullableText($payload, 'heading'),
            'categories' => Category::root()
                ->active()
                ->with(['children' => fn ($query) => $query->where('is_active', true)])
                ->orderBy('sort_order')
                ->limit($this->number($payload, 'limit', 6))
                ->get(),
        ];
    }
}
