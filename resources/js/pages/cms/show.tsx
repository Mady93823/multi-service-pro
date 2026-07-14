import { PageBlocks } from '@/components/blocks';
import { SeoHead } from '@/components/seo/seo-head';
import PublicLayout from '@/layouts/public-layout';
import { useTrans } from '@/lib/i18n';
import { type RenderedBlock, type SeoMetaProps } from '@/types';

interface Props {
    page: {
        title: string;
        slug: string;
        /** Server-sanitized by MarkdownRenderer (html_input: strip). Null when the page is built from blocks. */
        html: string | null;
        updated_at: string | null;
    };
    /** M20: a page carries either blocks or a markdown body, never both. */
    blocks: RenderedBlock[];
    meta: SeoMetaProps;
}

export default function CmsPageShow({ page, blocks, meta }: Props) {
    const t = useTrans();

    const updated = page.updated_at
        ? new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(page.updated_at))
        : null;

    if (blocks.length > 0) {
        return (
            <PublicLayout>
                <SeoHead meta={meta} />
                <PageBlocks blocks={blocks} />
            </PublicLayout>
        );
    }

    return (
        <PublicLayout>
            <SeoHead meta={meta} />
            <article className="mx-auto w-full max-w-3xl">
                <h1 className="text-3xl font-semibold tracking-tight">{page.title}</h1>
                {updated ? <p className="text-muted-foreground mt-1 text-xs">{t('Last updated :date', { date: updated })}</p> : null}
                <div
                    className="prose prose-neutral dark:prose-invert mt-6 max-w-none"
                    // Safe: produced by the server-side sanitizing renderer, never from client input.
                    dangerouslySetInnerHTML={{ __html: page.html ?? '' }}
                />
            </article>
        </PublicLayout>
    );
}
