<?php

namespace App\Domain\Blocks\Types;

use App\Domain\Blocks\Block;
use App\Domain\Blocks\BlockContext;
use App\Domain\Blocks\BlockField;
use App\Models\Sponsor;

class SponsorsBlock extends Block
{
    public function type(): string
    {
        return 'sponsors';
    }

    public function label(): string
    {
        return __('Sponsors');
    }

    public function fields(): array
    {
        return [
            BlockField::text('heading', __('Heading'), __('Trusted by')),
        ];
    }

    public function rules(): array
    {
        return [
            'heading' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function data(array $payload, BlockContext $context): ?array
    {
        return [
            'heading' => $this->nullableText($payload, 'heading'),
            'sponsors' => Sponsor::query()
                ->active()
                ->with('media')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        ];
    }
}
