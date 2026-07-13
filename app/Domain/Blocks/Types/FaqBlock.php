<?php

namespace App\Domain\Blocks\Types;

use App\Domain\Blocks\Block;
use App\Domain\Blocks\BlockContext;
use App\Domain\Blocks\BlockField;
use App\Models\Faq;

class FaqBlock extends Block
{
    public function type(): string
    {
        return 'faq';
    }

    public function label(): string
    {
        return __('FAQ');
    }

    public function fields(): array
    {
        return [
            BlockField::text('heading', __('Heading'), __('Frequently asked questions')),
            BlockField::number('limit', __('How many'), 10),
        ];
    }

    public function rules(): array
    {
        return [
            'heading' => ['nullable', 'string', 'max:120'],
            'limit' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function data(array $payload, BlockContext $context): ?array
    {
        return [
            'heading' => $this->nullableText($payload, 'heading'),
            'faqs' => Faq::query()
                ->active()
                ->limit($this->number($payload, 'limit', 10))
                ->get(),
        ];
    }
}
