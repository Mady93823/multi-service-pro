import { useTrans } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { Star } from 'lucide-react';
import { useState } from 'react';

const sizeClasses = {
    sm: 'size-3.5',
    md: 'size-4.5',
    lg: 'size-6',
} as const;

/** Read-only stars — rounds to the nearest whole star for fill. */
export function StarRating({ rating, size = 'sm', className }: { rating: number; size?: keyof typeof sizeClasses; className?: string }) {
    const rounded = Math.round(rating);

    return (
        <span className={cn('inline-flex items-center gap-0.5', className)} aria-hidden>
            {[1, 2, 3, 4, 5].map((star) => (
                <Star
                    key={star}
                    className={cn(sizeClasses[size], star <= rounded ? 'fill-amber-400 text-amber-400' : 'fill-muted text-muted-foreground/30')}
                />
            ))}
        </span>
    );
}

/** Interactive star picker for the review form. */
export function StarInput({ value, onChange }: { value: number; onChange: (value: number) => void }) {
    const t = useTrans();
    const [hovered, setHovered] = useState(0);
    const active = hovered > 0 ? hovered : value;

    return (
        <div className="flex items-center gap-1" onMouseLeave={() => setHovered(0)}>
            {[1, 2, 3, 4, 5].map((star) => (
                <button
                    key={star}
                    type="button"
                    aria-label={t(':count stars', { count: String(star) })}
                    onMouseEnter={() => setHovered(star)}
                    onFocus={() => setHovered(star)}
                    onBlur={() => setHovered(0)}
                    onClick={() => onChange(star)}
                    className="focus-visible:ring-ring rounded-sm p-0.5 transition-transform hover:scale-110 focus-visible:ring-2 focus-visible:outline-none"
                >
                    <Star className={cn('size-7', star <= active ? 'fill-amber-400 text-amber-400' : 'fill-muted text-muted-foreground/30')} />
                </button>
            ))}
        </div>
    );
}
