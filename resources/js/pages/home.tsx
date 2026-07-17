import { PageBlocks } from '@/components/blocks';
import { SeoHead } from '@/components/seo/seo-head';
import LocationAutoDetect from '@/components/site/location-auto-detect';
import PublicLayout from '@/layouts/public-layout';
import { type RenderedBlock, type SeoMetaProps } from '@/types';

interface HomeProps {
    blocks: RenderedBlock[];
    meta: SeoMetaProps;
    /** LocalBusiness JSON-LD, or null when structured data is switched off (M24). */
    schema: Record<string, unknown> | null;
}

/**
 * The storefront home page (M20): whatever blocks the admin assembled, in
 * order. No section here is hardcoded — including the title and meta tags,
 * which now come from the SEO settings (M24).
 */
export default function Home({ blocks, meta, schema }: HomeProps) {
    return (
        // Bleed: every block draws its own band and its own container (M20 +
        // the storefront `Section` primitive), so the shell must not clamp them.
        <PublicLayout bleed>
            <SeoHead meta={meta} schema={schema} />
            <LocationAutoDetect />
            <PageBlocks blocks={blocks} />
        </PublicLayout>
    );
}
