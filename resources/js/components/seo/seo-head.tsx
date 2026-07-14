import { type SeoMetaProps } from '@/types';
import { Head } from '@inertiajs/react';
import { useEffect } from 'react';

interface SeoHeadProps {
    meta: SeoMetaProps;
    /** schema.org JSON-LD, already assembled on the server. Null when schema is switched off. */
    schema?: Record<string, unknown> | null;
}

/**
 * The meta block every public page renders (M24).
 *
 * The JSON-LD is injected as a real DOM node with `textContent`, not through
 * Inertia's <Head> and not with dangerouslySetInnerHTML: React does not execute
 * a <script> it renders into the tree, and innerHTML would have to escape the
 * payload by hand. `JSON.stringify` is the escaping — no admin string is ever
 * interpolated into markup (the same technique as M19's custom code, D26).
 */
export function SeoHead({ meta, schema = null }: SeoHeadProps) {
    useEffect(() => {
        if (schema === null) {
            return;
        }

        const node = document.createElement('script');
        node.type = 'application/ld+json';
        node.textContent = JSON.stringify(schema);
        document.head.appendChild(node);

        return () => node.remove();
    }, [schema]);

    return (
        <Head title={meta.title}>
            {meta.description !== null && <meta name="description" content={meta.description} />}
            <meta property="og:type" content={meta.type} />
            <meta property="og:site_name" content={meta.site_name} />
            <meta property="og:title" content={meta.title} />
            {meta.description !== null && <meta property="og:description" content={meta.description} />}
            <meta property="og:url" content={meta.url} />
            {meta.image !== null && <meta property="og:image" content={meta.image} />}
            <meta name="twitter:card" content={meta.image !== null ? 'summary_large_image' : 'summary'} />
            <link rel="canonical" href={meta.url} />
        </Head>
    );
}
