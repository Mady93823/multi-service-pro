<?php

namespace App\Domain\Blocks\Types;

use App\Domain\Blocks\Block;
use App\Domain\Blocks\BlockContext;
use App\Domain\Blocks\BlockField;

class StatsBlock extends Block
{
    public function type(): string
    {
        return 'stats';
    }

    public function label(): string
    {
        return __('Counters');
    }

    public function fields(): array
    {
        return [
            BlockField::text('heading', __('Heading')),
            BlockField::repeater('items', __('Counters'), [
                // Free text, not a number: "10k+", "4.8★" and "24/7" are the
                // figures marketing actually wants on the wall.
                BlockField::text('value', __('Figure')),
                BlockField::text('label', __('Label')),
            ]),
        ];
    }

    public function rules(): array
    {
        return [
            'heading' => ['nullable', 'string', 'max:120'],
            'items' => ['array', 'max:8'],
            'items.*.value' => ['required', 'string', 'max:20'],
            'items.*.label' => ['required', 'string', 'max:60'],
        ];
    }

    public function data(array $payload, BlockContext $context): ?array
    {
        return [
            'heading' => $this->nullableText($payload, 'heading'),
            'items' => array_map(fn (array $row): array => [
                'value' => $this->text($row, 'value'),
                'label' => $this->text($row, 'label'),
            ], $this->rows($payload, 'items')),
        ];
    }
}
