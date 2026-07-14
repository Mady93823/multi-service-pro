import { Container } from '@/components/site/section';
import { cn } from '@/lib/utils';

export interface SpacerProps {
    size: string;
    divider: boolean;
}

const HEIGHTS: Record<string, string> = {
    sm: 'h-6',
    md: 'h-12',
    lg: 'h-24',
};

/**
 * The divider is drawn inside the container, not across the viewport: a rule
 * that runs edge to edge is a section boundary, and that is what `Section`'s
 * tones are for.
 */
export function SpacerBlock({ size, divider }: SpacerProps) {
    return (
        <Container>
            <div className={cn('flex items-center', HEIGHTS[size] ?? HEIGHTS.md)} aria-hidden>
                {divider && <hr className="w-full" />}
            </div>
        </Container>
    );
}
