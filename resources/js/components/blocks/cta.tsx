import { Container } from '@/components/site/section';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export interface CtaProps {
    heading: string;
    subheading: string | null;
    button_label: string | null;
    button_url: string | null;
    image_url: string | null;
}

/**
 * The closing ask. It sits inside the page's column rather than bleeding —
 * a card that ends the page reads as an invitation; a full-bleed band there
 * reads as another section, and the page never lands.
 */
export function CtaBlock({ heading, subheading, button_label, button_url, image_url }: CtaProps) {
    const hasImage = image_url !== null;

    return (
        <Container className="py-12 sm:py-16">
            <div
                className={cn(
                    'relative isolate overflow-hidden rounded-3xl px-6 py-16 text-center sm:px-12',
                    hasImage ? 'text-white' : 'bg-primary text-primary-foreground',
                )}
            >
                {hasImage ? (
                    <>
                        <img src={image_url} alt="" className="absolute inset-0 -z-10 h-full w-full object-cover" />
                        <div className="absolute inset-0 -z-10 bg-black/60" />
                    </>
                ) : (
                    <div className="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,0.18),transparent_55%)]" />
                )}

                <div className="mx-auto max-w-2xl">
                    <h2 className="text-3xl font-bold tracking-tight sm:text-4xl">{heading}</h2>
                    {subheading !== null && <p className="mt-4 text-lg opacity-85">{subheading}</p>}

                    {button_label !== null && button_url !== null && (
                        <Button
                            asChild
                            size="lg"
                            variant={hasImage ? 'default' : 'secondary'}
                            className="mt-8 h-12 rounded-xl px-8 text-base font-semibold shadow-lg"
                        >
                            <a href={button_url}>{button_label}</a>
                        </Button>
                    )}
                </div>
            </div>
        </Container>
    );
}
