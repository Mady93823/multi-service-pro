import { cn } from '@/lib/utils';

export interface RichTextProps {
    html: string;
    width: string;
}

/**
 * The HTML comes from MarkdownRenderer (D20), which strips raw HTML at the
 * source — this is the same trusted-by-construction string the CMS pages inject.
 */
export function RichTextBlock({ html, width }: RichTextProps) {
    return (
        <section className={cn('py-6', width === 'narrow' && 'mx-auto max-w-3xl')}>
            <div className="prose prose-neutral dark:prose-invert max-w-none" dangerouslySetInnerHTML={{ __html: html }} />
        </section>
    );
}
