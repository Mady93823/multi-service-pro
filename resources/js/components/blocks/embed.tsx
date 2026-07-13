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
        <section className="space-y-4 py-6">
            {heading !== null && <h2 className="text-lg font-semibold">{heading}</h2>}
            <div className="aspect-video overflow-hidden rounded-xl border">
                <iframe
                    src={src}
                    title={heading ?? caption ?? 'embed'}
                    loading="lazy"
                    allowFullScreen
                    referrerPolicy="strict-origin-when-cross-origin"
                    className="h-full w-full"
                />
            </div>
            {caption !== null && <p className="text-muted-foreground text-sm">{caption}</p>}
        </section>
    );
}
