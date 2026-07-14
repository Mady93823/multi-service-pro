import { Section, SectionHeading } from '@/components/site/section';

export interface EmbedProps {
    heading: string | null;
    src: string;
    caption: string | null;
}

/**
 * `src` is derived on the server from an allowlist of hosts — never the raw
 * string an admin pasted (see EmbedBlock).
 */
export function EmbedBlock({ heading, src, caption }: EmbedProps) {
    return (
        <Section spacing="md">
            {heading !== null && <SectionHeading title={heading} align="center" />}

            <div className="mx-auto max-w-4xl">
                <div className="aspect-video overflow-hidden rounded-2xl border shadow-sm">
                    <iframe
                        src={src}
                        title={heading ?? caption ?? 'embed'}
                        loading="lazy"
                        allowFullScreen
                        referrerPolicy="strict-origin-when-cross-origin"
                        className="h-full w-full"
                    />
                </div>
                {caption !== null && <p className="text-muted-foreground mt-3 text-center text-sm">{caption}</p>}
            </div>
        </Section>
    );
}
