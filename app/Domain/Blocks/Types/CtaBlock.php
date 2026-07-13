<?php

namespace App\Domain\Blocks\Types;

use App\Domain\Blocks\Block;
use App\Domain\Blocks\BlockContext;
use App\Domain\Blocks\BlockField;

class CtaBlock extends Block
{
    public function type(): string
    {
        return 'cta';
    }

    public function label(): string
    {
        return __('Call to action');
    }

    public function fields(): array
    {
        return [
            BlockField::text('heading', __('Heading')),
            BlockField::textarea('subheading', __('Sub-copy')),
            BlockField::text('button_label', __('Button label'), ''),
            BlockField::text('button_url', __('Button link'), '/services'),
            BlockField::media(__('Background image')),
        ];
    }

    public function rules(): array
    {
        return [
            'heading' => ['required', 'string', 'max:120'],
            'subheading' => ['nullable', 'string', 'max:300'],
            'button_label' => ['nullable', 'string', 'max:40'],
            'button_url' => ['nullable', 'string', 'max:2048', 'regex:#^(https?://|/)#'],
            'media_asset_id' => ['nullable', 'integer', 'exists:media_assets,id'],
        ];
    }

    public function data(array $payload, BlockContext $context): ?array
    {
        return [
            'heading' => $this->text($payload, 'heading'),
            'subheading' => $this->nullableText($payload, 'subheading'),
            'button_label' => $this->nullableText($payload, 'button_label'),
            'button_url' => $this->nullableText($payload, 'button_url'),
            'image_url' => $context->imageUrl($payload['media_asset_id'] ?? null, 'original'),
        ];
    }
}
