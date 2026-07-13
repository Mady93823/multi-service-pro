<?php

namespace App\Domain\Blocks;

use App\Domain\Blocks\Types\BannersBlock;
use App\Domain\Blocks\Types\CategoriesGridBlock;
use App\Domain\Blocks\Types\CtaBlock;
use App\Domain\Blocks\Types\EmbedBlock;
use App\Domain\Blocks\Types\FaqBlock;
use App\Domain\Blocks\Types\GalleryBlock;
use App\Domain\Blocks\Types\HeroBlock;
use App\Domain\Blocks\Types\RichTextBlock;
use App\Domain\Blocks\Types\ServicesGridBlock;
use App\Domain\Blocks\Types\SpacerBlock;
use App\Domain\Blocks\Types\SponsorsBlock;
use App\Domain\Blocks\Types\StatsBlock;
use App\Domain\Blocks\Types\StepsBlock;
use App\Domain\Blocks\Types\TestimonialsBlock;

/**
 * Every block type the page builder knows (M20, ADR D22), in the order the
 * "add block" menu shows them.
 *
 * A type that is not here is **unknown**: it cannot be saved (the request
 * rejects it) and, if a row already carries it, it renders nothing. Removing a
 * block type from this list degrades the pages that used it to a gap — never a
 * 500 on a public page.
 */
class BlockRegistry
{
    /** @var list<class-string<Block>> */
    private const BLOCKS = [
        HeroBlock::class,
        RichTextBlock::class,
        BannersBlock::class,
        CategoriesGridBlock::class,
        ServicesGridBlock::class,
        StepsBlock::class,
        StatsBlock::class,
        GalleryBlock::class,
        TestimonialsBlock::class,
        SponsorsBlock::class,
        FaqBlock::class,
        CtaBlock::class,
        EmbedBlock::class,
        SpacerBlock::class,
    ];

    /** @var array<string, Block>|null */
    private ?array $resolved = null;

    /**
     * @return array<string, Block>
     */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $blocks = [];

        foreach (self::BLOCKS as $class) {
            $block = app($class);
            $blocks[$block->type()] = $block;
        }

        return $this->resolved = $blocks;
    }

    public function find(string $type): ?Block
    {
        return $this->all()[$type] ?? null;
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys($this->all());
    }

    /**
     * The admin form is rendered from this — one schema, no hand-written forms.
     *
     * @return list<array<string, mixed>>
     */
    public function schema(): array
    {
        return array_values(array_map(fn (Block $block): array => $block->schema(), $this->all()));
    }
}
