<?php

namespace App\Domain\Blocks\Types;

use App\Domain\Blocks\Block;
use App\Domain\Blocks\BlockContext;
use App\Domain\Blocks\BlockField;

class HeroBlock extends Block
{
    public function type(): string
    {
        return 'hero';
    }

    public function label(): string
    {
        return __('Hero');
    }

    public function fields(): array
    {
        return [
            BlockField::text('heading', __('Heading')),
            BlockField::textarea('subheading', __('Sub-copy')),
            BlockField::media(__('Background image')),
            BlockField::toggle('show_search', __('Show the service search box'), true),
            BlockField::text('cta_label', __('Button label')),
            BlockField::text('cta_url', __('Button link'), '/services'),
            BlockField::select('align', __('Alignment'), [
                ['value' => 'center', 'label' => __('Centered')],
                ['value' => 'left', 'label' => __('Left')],
            ], 'center'),
        ];
    }

    public function rules(): array
    {
        return [
            'heading' => ['required', 'string', 'max:120'],
            'subheading' => ['nullable', 'string', 'max:400'],
            'media_asset_id' => ['nullable', 'integer', 'exists:media_assets,id'],
            'show_search' => ['boolean'],
            'cta_label' => ['nullable', 'string', 'max:40'],
            // An href is a script sink — same rule as menus and banners (D30).
            'cta_url' => ['nullable', 'string', 'max:2048', 'regex:#^(https?://|/)#'],
            'align' => ['required', 'string', 'in:center,left'],
        ];
    }

    public function data(array $payload, BlockContext $context): ?array
    {
        return [
            'heading' => $this->text($payload, 'heading'),
            'subheading' => $this->nullableText($payload, 'subheading'),
            'image_url' => $context->imageUrl($payload['media_asset_id'] ?? null, 'original'),
            'show_search' => $this->flag($payload, 'show_search', true),
            'cta_label' => $this->nullableText($payload, 'cta_label'),
            'cta_url' => $this->nullableText($payload, 'cta_url'),
            'align' => $this->text($payload, 'align', 'center'),
        ];
    }
}
