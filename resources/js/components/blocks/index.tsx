import { BannersBlock, type BannersProps } from '@/components/blocks/banners';
import { CategoriesGridBlock, type CategoriesGridProps } from '@/components/blocks/categories-grid';
import { CtaBlock, type CtaProps } from '@/components/blocks/cta';
import { EmbedBlock, type EmbedProps } from '@/components/blocks/embed';
import { FaqBlock, type FaqProps } from '@/components/blocks/faq';
import { GalleryBlock, type GalleryProps } from '@/components/blocks/gallery';
import { HeroBlock, type HeroProps } from '@/components/blocks/hero';
import { RichTextBlock, type RichTextProps } from '@/components/blocks/rich-text';
import { ServicesGridBlock, type ServicesGridProps } from '@/components/blocks/services-grid';
import { SpacerBlock, type SpacerProps } from '@/components/blocks/spacer';
import { SponsorsBlock, type SponsorsProps } from '@/components/blocks/sponsors';
import { StatsBlock, type StatsProps } from '@/components/blocks/stats';
import { StepsBlock, type StepsProps } from '@/components/blocks/steps';
import { TestimonialsBlock, type TestimonialsProps } from '@/components/blocks/testimonials';
import { type RenderedBlock } from '@/types';
import { Fragment, type ReactNode } from 'react';

type Renderer = (props: Record<string, unknown>) => ReactNode;

/**
 * The React half of the block registry (M20, ADR D22). Its keys mirror
 * `BlockRegistry`'s — an arch test fails the build if a PHP block type has no
 * renderer here.
 *
 * The props were built by the block that owns the type and validated on write,
 * which is what makes the cast below safe.
 */
const RENDERERS: Record<string, Renderer> = {
    hero: (props) => <HeroBlock {...(props as unknown as HeroProps)} />,
    rich_text: (props) => <RichTextBlock {...(props as unknown as RichTextProps)} />,
    banners: (props) => <BannersBlock {...(props as unknown as BannersProps)} />,
    categories_grid: (props) => <CategoriesGridBlock {...(props as unknown as CategoriesGridProps)} />,
    services_grid: (props) => <ServicesGridBlock {...(props as unknown as ServicesGridProps)} />,
    steps: (props) => <StepsBlock {...(props as unknown as StepsProps)} />,
    stats: (props) => <StatsBlock {...(props as unknown as StatsProps)} />,
    gallery: (props) => <GalleryBlock {...(props as unknown as GalleryProps)} />,
    testimonials: (props) => <TestimonialsBlock {...(props as unknown as TestimonialsProps)} />,
    sponsors: (props) => <SponsorsBlock {...(props as unknown as SponsorsProps)} />,
    faq: (props) => <FaqBlock {...(props as unknown as FaqProps)} />,
    cta: (props) => <CtaBlock {...(props as unknown as CtaProps)} />,
    embed: (props) => <EmbedBlock {...(props as unknown as EmbedProps)} />,
    spacer: (props) => <SpacerBlock {...(props as unknown as SpacerProps)} />,
};

/**
 * A type with no renderer draws **nothing** — a removed or renamed block leaves
 * a gap in the page, never an exception on a public screen.
 */
export function PageBlocks({ blocks }: { blocks: RenderedBlock[] }) {
    return (
        <>
            {blocks.map((block) => {
                const render = RENDERERS[block.type];

                return render === undefined ? null : <Fragment key={block.id}>{render(block.props)}</Fragment>;
            })}
        </>
    );
}
