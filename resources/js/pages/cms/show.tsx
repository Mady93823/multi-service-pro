import PublicLayout from '@/layouts/public-layout';
import { useTrans } from '@/lib/i18n';
import { Head } from '@inertiajs/react';

interface Props {
    page: {
        title: string;
        slug: string;
        /** Server-sanitized by MarkdownRenderer (html_input: strip) — the only HTML source this page injects. */
        html: string;
        updated_at: string | null;
    };
}

export default function CmsPageShow({ page }: Props) {
    const t = useTrans();

    const updated = page.updated_at
        ? new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(page.updated_at))
        : null;

    return (
        <PublicLayout>
            <Head title={page.title} />
            <article className="mx-auto w-full max-w-3xl">
                <h1 className="text-3xl font-semibold tracking-tight">{page.title}</h1>
                {updated ? <p className="text-muted-foreground mt-1 text-xs">{t('Last updated :date', { date: updated })}</p> : null}
                <div
                    className="prose prose-neutral dark:prose-invert mt-6 max-w-none"
                    // Safe: produced by the server-side sanitizing renderer, never from client input.
                    dangerouslySetInnerHTML={{ __html: page.html }}
                />
            </article>
        </PublicLayout>
    );
}
