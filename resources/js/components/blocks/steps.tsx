export interface StepsProps {
    heading: string | null;
    items: { title: string; description: string | null }[];
}

export function StepsBlock({ heading, items }: StepsProps) {
    if (items.length === 0) {
        return null;
    }

    return (
        <section className="space-y-6 py-8">
            {heading !== null && <h2 className="text-lg font-semibold">{heading}</h2>}
            <ol className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                {items.map((item, index) => (
                    <li key={index} className="flex gap-4">
                        <span className="bg-primary text-primary-foreground flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold">
                            {index + 1}
                        </span>
                        <div>
                            <h3 className="font-medium">{item.title}</h3>
                            {item.description !== null && <p className="text-muted-foreground mt-1 text-sm">{item.description}</p>}
                        </div>
                    </li>
                ))}
            </ol>
        </section>
    );
}
