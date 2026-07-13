<?php

namespace App\Domain\Blocks\Types;

use App\Domain\Blocks\Block;
use App\Domain\Blocks\BlockContext;
use App\Domain\Blocks\BlockField;
use App\Models\Category;
use App\Models\Service;

class ServicesGridBlock extends Block
{
    public function type(): string
    {
        return 'services_grid';
    }

    public function label(): string
    {
        return __('Services grid');
    }

    public function fields(): array
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $category): array => ['value' => (string) $category->id, 'label' => $category->name])
            ->all();

        return [
            BlockField::text('heading', __('Heading'), __('Popular services')),
            BlockField::toggle('featured_only', __('Featured services only'), true),
            BlockField::select('category_id', __('Category'), [
                ['value' => '', 'label' => __('All categories')],
                ...$categories,
            ], ''),
            BlockField::number('limit', __('How many'), 8),
        ];
    }

    public function rules(): array
    {
        return [
            'heading' => ['nullable', 'string', 'max:120'],
            'featured_only' => ['boolean'],
            'category_id' => ['nullable', 'string', 'exists:categories,id'],
            'limit' => ['required', 'integer', 'min:1', 'max:24'],
        ];
    }

    public function data(array $payload, BlockContext $context): ?array
    {
        $categoryId = $this->nullableText($payload, 'category_id');

        return [
            'heading' => $this->nullableText($payload, 'heading'),
            'services' => Service::query()
                ->active()
                // The zone gate follows the visitor's default address (M03), so a
                // block cannot advertise a service that cannot be booked there.
                ->inZone($context->zoneId)
                ->when($this->flag($payload, 'featured_only', true), fn ($query) => $query->where('is_featured', true))
                ->when($categoryId !== null, fn ($query) => $query->where('category_id', $categoryId))
                ->whereHas('category', fn ($query) => $query->where('is_active', true))
                ->with(['category', 'media'])
                ->orderBy('sort_order')
                ->limit($this->number($payload, 'limit', 8))
                ->get(),
        ];
    }
}
