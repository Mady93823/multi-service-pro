<?php

namespace App\Domain\Blocks\Types;

use App\Domain\Banners\Enums\BannerPlacement;
use App\Domain\Blocks\Block;
use App\Domain\Blocks\BlockContext;
use App\Domain\Blocks\BlockField;
use App\Models\Banner;

/**
 * The M12 banners, placed on a page by a block. The banners themselves stay
 * where they are managed (Marketing → Banners) — the block only says *where*
 * on the page a placement appears.
 */
class BannersBlock extends Block
{
    public function type(): string
    {
        return 'banners';
    }

    public function label(): string
    {
        return __('Banners');
    }

    public function fields(): array
    {
        return [
            BlockField::select('placement', __('Placement'), [
                ['value' => BannerPlacement::HomeHero->value, 'label' => __('Hero (large, rotating)')],
                ['value' => BannerPlacement::HomeStrip->value, 'label' => __('Strip (small, scrolling)')],
            ], BannerPlacement::HomeHero->value),
        ];
    }

    public function rules(): array
    {
        return [
            'placement' => ['required', 'string', 'in:'.implode(',', array_column(BannerPlacement::cases(), 'value'))],
        ];
    }

    public function data(array $payload, BlockContext $context): ?array
    {
        $placement = BannerPlacement::tryFrom($this->text($payload, 'placement'));

        if ($placement === null) {
            return null;
        }

        return [
            'placement' => $placement->value,
            'banners' => Banner::query()
                ->live()
                ->where('placement', $placement->value)
                ->with('media')
                ->orderBy('sort_order')
                ->get(),
        ];
    }
}
