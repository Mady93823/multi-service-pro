import { Section } from '@/components/site/section';
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
        <Section spacing="md">
            <div
                className={cn(
                    'prose prose-neutral dark:prose-invert prose-headings:tracking-tight max-w-none',
                    width === 'narrow' && 'mx-auto max-w-3xl',
                )}
                dangerouslySetInnerHTML={{ __html: html }}
            />
        </Section>
    );
}
