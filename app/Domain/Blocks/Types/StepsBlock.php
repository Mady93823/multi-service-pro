<?php

namespace App\Domain\Blocks\Types;

use App\Domain\Blocks\Block;
use App\Domain\Blocks\BlockContext;
use App\Domain\Blocks\BlockField;

class StepsBlock extends Block
{
    public function type(): string
    {
        return 'steps';
    }

    public function label(): string
    {
        return __('How it works');
    }

    public function fields(): array
    {
        return [
            BlockField::text('heading', __('Heading'), __('How it works')),
            BlockField::repeater('items', __('Steps'), [
                BlockField::text('title', __('Title')),
                BlockField::textarea('description', __('Description')),
            ]),
        ];
    }

    public function rules(): array
    {
        return [
            'heading' => ['nullable', 'string', 'max:120'],
            'items' => ['array', 'max:8'],
            'items.*.title' => ['required', 'string', 'max:80'],
            'items.*.description' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function data(array $payload, BlockContext $context): ?array
    {
        return [
            'heading' => $this->nullableText($payload, 'heading'),
            'items' => array_map(fn (array $row): array => [
                'title' => $this->text($row, 'title'),
                'description' => $this->nullableText($row, 'description'),
            ], $this->rows($payload, 'items')),
        ];
    }
}
