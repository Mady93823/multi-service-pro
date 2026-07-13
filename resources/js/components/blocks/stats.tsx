export interface StatsProps {
    heading: string | null;
    items: { value: string; label: string }[];
}

export function StatsBlock({ heading, items }: StatsProps) {
    if (items.length === 0) {
        return null;
    }

    return (
        <section className="space-y-6 py-8">
            {heading !== null && <h2 className="text-lg font-semibold">{heading}</h2>}
            <div className="grid gap-6 rounded-2xl border p-6 sm:grid-cols-2 lg:grid-cols-4">
                {items.map((item, index) => (
                    <div key={index} className="text-center">
                        <p className="text-3xl font-semibold tracking-tight">{item.value}</p>
                        <p className="text-muted-foreground mt-1 text-sm">{item.label}</p>
                    </div>
                ))}
            </div>
        </section>
    );
}
