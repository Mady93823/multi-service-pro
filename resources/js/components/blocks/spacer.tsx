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

export function SpacerBlock({ size, divider }: SpacerProps) {
    return (
        <div className={cn('flex items-center', HEIGHTS[size] ?? HEIGHTS.md)} aria-hidden>
            {divider && <hr className="w-full" />}
        </div>
    );
}
