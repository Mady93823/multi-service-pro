import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export interface CtaProps {
    heading: string;
    subheading: string | null;
    button_label: string | null;
    button_url: string | null;
    image_url: string | null;
}

export function CtaBlock({ heading, subheading, button_label, button_url, image_url }: CtaProps) {
    return (
        <section className={cn('relative overflow-hidden rounded-2xl px-6 py-12 text-center', image_url !== null ? 'text-white' : 'bg-muted/40')}>
            {image_url !== null && (
                <>
                    <img src={image_url} alt="" className="absolute inset-0 h-full w-full object-cover" />
                    <div className="absolute inset-0 bg-black/55" />
                </>
            )}

            <div className="relative mx-auto max-w-2xl">
                <h2 className="text-2xl font-semibold tracking-tight">{heading}</h2>
                {subheading !== null && <p className={cn('mt-2', image_url !== null ? 'text-white/85' : 'text-muted-foreground')}>{subheading}</p>}
                {button_label !== null && button_url !== null && (
                    <Button asChild size="lg" className="mt-6">
                        <a href={button_url}>{button_label}</a>
                    </Button>
                )}
            </div>
        </section>
    );
}
