import { PageBlocks } from '@/components/blocks';
import PublicLayout from '@/layouts/public-layout';
import { useTrans } from '@/lib/i18n';
import { type RenderedBlock } from '@/types';
import { Head } from '@inertiajs/react';

/**
 * The storefront home page (M20): whatever blocks the admin assembled, in
 * order. No section here is hardcoded.
 */
export default function Home({ blocks }: { blocks: RenderedBlock[] }) {
    const t = useTrans();

    return (
        <PublicLayout>
            <Head title={t('Home services, on demand')} />
            <PageBlocks blocks={blocks} />
        </PublicLayout>
    );
}
