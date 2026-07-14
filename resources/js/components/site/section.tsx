import { cn } from '@/lib/utils';
import { type ReactNode } from 'react';

/**
 * The storefront's spine.
 *
 * Before this, every marketing section was a `py-4` div with a `text-lg`
 * heading — a section title at the size of a form label — and all of it was
 * trapped inside one `max-w-7xl` main, so nothing could take a tinted band or
 * bleed to the edge. A page read as one undifferentiated scroll.
 *
 * Two pieces, on purpose:
 *
 * - `Section` owns the **band**: full-bleed background, vertical rhythm, tone.
 * - `Container` owns the **measure**: the column the content actually sits in.
 *
 * A section that wants a coloured band still keeps its text on the same
 * gridline as every other section, because the band and the measure are
 * separate objects.
 */

const TONES = {
    /** Page background. */
    plain: 'bg-background',
    /** The band that separates one section from the next. */
    surface: 'bg-surface',
    /** Brand wash — for the one or two sections that are asking for something. */
    brand: 'bg-primary/5',
    /** Inverted: heavy, and therefore rationed. */
    contrast: 'bg-foreground text-background',
} as const;

const SPACING = {
    none: '',
    sm: 'py-8 sm:py-10',
    md: 'py-12 sm:py-16',
    lg: 'py-16 sm:py-24',
} as const;

interface SectionProps {
    children: ReactNode;
    tone?: keyof typeof TONES;
    spacing?: keyof typeof SPACING;
    /** Skip the inner container — the child is laying out its own full-bleed content. */
    bleed?: boolean;
    className?: string;
    'aria-label'?: string;
}

export function Section({ children, tone = 'plain', spacing = 'md', bleed = false, className, ...rest }: SectionProps) {
    return (
        <section className={cn(TONES[tone], SPACING[spacing], className)} {...rest}>
            {bleed ? children : <Container>{children}</Container>}
        </section>
    );
}

export function Container({ children, className }: { children: ReactNode; className?: string }) {
    return <div className={cn('mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8', className)}>{children}</div>;
}

interface SectionHeadingProps {
    /** The small label above the title. Says what kind of thing this section is. */
    eyebrow?: string | null;
    title: string;
    description?: string | null;
    /** A link or button that belongs to the section, e.g. "See all". */
    action?: ReactNode;
    align?: 'left' | 'center';
    className?: string;
}

export function SectionHeading({ eyebrow = null, title, description = null, action, align = 'left', className }: SectionHeadingProps) {
    const centered = align === 'center';

    return (
        <div
            className={cn(
                'mb-8 gap-4 sm:mb-10',
                centered ? 'mx-auto max-w-2xl text-center' : 'flex flex-col sm:flex-row sm:items-end sm:justify-between',
                className,
            )}
        >
            <div className={cn('space-y-2', centered && 'space-y-3')}>
                {eyebrow !== null && eyebrow !== '' && <p className="text-primary text-xs font-semibold tracking-[0.14em] uppercase">{eyebrow}</p>}
                <h2 className="text-2xl font-semibold tracking-tight sm:text-3xl">{title}</h2>
                {description !== null && description !== '' && (
                    <p className={cn('text-muted-foreground max-w-2xl', centered && 'mx-auto')}>{description}</p>
                )}
            </div>

            {action !== undefined && <div className={cn('shrink-0', centered && 'mt-6 flex justify-center')}>{action}</div>}
        </div>
    );
}
