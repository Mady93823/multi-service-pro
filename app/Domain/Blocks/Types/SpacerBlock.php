<?php

namespace App\Domain\Blocks\Types;

use App\Domain\Blocks\Block;
use App\Domain\Blocks\BlockContext;
use App\Domain\Blocks\BlockField;

class SpacerBlock extends Block
{
    public function type(): string
    {
        return 'spacer';
    }

    public function label(): string
    {
        return __('Spacer');
    }

    public function fields(): array
    {
        return [
            BlockField::select('size', __('Height'), [
                ['value' => 'sm', 'label' => __('Small')],
                ['value' => 'md', 'label' => __('Medium')],
                ['value' => 'lg', 'label' => __('Large')],
            ], 'md'),
            BlockField::toggle('divider', __('Show a divider line'), false),
        ];
    }

    public function rules(): array
    {
        return [
            'size' => ['required', 'string', 'in:sm,md,lg'],
            'divider' => ['boolean'],
        ];
    }

    public function data(array $payload, BlockContext $context): ?array
    {
        return [
            'size' => $this->text($payload, 'size', 'md'),
            'divider' => $this->flag($payload, 'divider'),
        ];
    }
}
