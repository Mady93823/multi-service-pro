<?php

namespace App\Domain\Blocks\Types;

use App\Domain\Blocks\Block;
use App\Domain\Blocks\BlockContext;
use App\Domain\Blocks\BlockField;
use App\Models\Testimonial;

class TestimonialsBlock extends Block
{
    public function type(): string
    {
        return 'testimonials';
    }

    public function label(): string
    {
        return __('Testimonials');
    }

    public function fields(): array
    {
        return [
            BlockField::text('heading', __('Heading'), __('What our customers say')),
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
            'testimonials' => Testimonial::query()
                ->active()
                ->with('media')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->limit($this->number($payload, 'limit', 6))
                ->get(),
        ];
    }
}
