export interface GalleryProps {
    heading: string | null;
    items: { url: string; thumb_url: string; caption: string | null }[];
}

export function GalleryBlock({ heading, items }: GalleryProps) {
    if (items.length === 0) {
        return null;
    }

    return (
        <section className="space-y-4 py-6">
            {heading !== null && <h2 className="text-lg font-semibold">{heading}</h2>}
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {items.map((item, index) => (
                    <figure key={index} className="overflow-hidden rounded-xl border">
                        <img src={item.thumb_url} alt={item.caption ?? ''} loading="lazy" className="h-48 w-full object-cover" />
                        {item.caption !== null && <figcaption className="text-muted-foreground px-3 py-2 text-sm">{item.caption}</figcaption>}
                    </figure>
                ))}
            </div>
        </section>
    );
}
